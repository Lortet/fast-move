<?php
/**
 * Move Plugin Operation Planner
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Hamann <michael@content-space.de>
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * Class helper_plugin_move_plan
 *
 * This thing prepares and keeps progress info on complex move operations (eg. where more than a single
 * object is affected).
 *
 * Please note: this has not a complex move resolver. Move operations may not depend on each other (eg. you
 * can not use a namespace as source that will only be created by a different move operation) instead all given
 * operations should be operations on the current state to come to a wanted future state. The tree manager takes
 * care of that by abstracting all moves on a DOM representation first, then submitting the needed changes (eg.
 * differences between now and wanted).
 *
 * Glossary:
 *
 *   document - refers to either a page or a media file here
 */
class helper_plugin_move_plan extends DokuWiki_Plugin {
    /** Number of operations per step  */
    const OPS_PER_RUN = 10;

    const TYPE_PAGES = 1;
    const TYPE_MEDIA = 2;
    const CLASS_NS   = 4;
    const CLASS_DOC  = 8;

    /**
     * @var array the options for this move plan
     */
    protected $options = array(); // defaults are set in loadOptions()

    /**
     * @var array holds the location of the different list and state files
     */
    protected $files = array();

    /**
     * @var array the planned moves
     */
    protected $plan = array();

    /**
     * @var array temporary holder of document lists
     */
    protected $tmpstore = array(
        'pages' => array(),
        'media' => array(),
        'ns'    => array(),
        'affpg' => array(),
        'miss'  => array(),
        'miss_media'  => array(),
        'startpages' => array(),
    );

    /** @var helper_plugin_move_op $MoveOperator */
    protected $MoveOperator = null;

    /** @var string|null Current operation log file */
    protected $logFile = null;

    /** @var float Request start time used to bound work by PHP's execution limit */
    protected $executionStartedAt = 0.0;

    /**
     * Constructor
     *
     * initializes state (if any) for continuiation of a running move op
     */
    public function __construct() {
        global $conf;

        // set the file locations
        $this->files = array(
            'opts'       => $conf['metadir'] . '/__move_opts',
            'pagelist'   => $conf['metadir'] . '/__move_pagelist',
            'medialist'  => $conf['metadir'] . '/__move_medialist',
            'affected'   => $conf['metadir'] . '/__move_affected',
            'namespaces' => $conf['metadir'] . '/__move_namespaces',
            'missing'    => $conf['metadir'] . '/__move_missing',
            'missing_media'    => $conf['metadir'] . '/__move_missing_media',
            'copymap'    => $conf['metadir'] . '/__move_copymap',
            'startpages' => $conf['metadir'] . '/__move_startpages',
        );

        $this->MoveOperator = plugin_load('helper', 'move_op');

        $this->loadOptions();
        if($this->options['started']) {
            $this->logFile = $this->getLogFile($this->options['started']);
        }
    }

    /**
     * Load the current options if any
     *
     * If no options are found, the default options will be extended by any available
     * config options
     */
    protected function loadOptions() {
        // (re)set defaults
        $this->options = array(
            // status
            'committed'   => false,
            'started'     => 0,

            // counters
            'pages_all'   => 0,
            'pages_run'   => 0,
            'media_all'   => 0,
            'media_run'   => 0,
            'affpg_all'   => 0,
            'affpg_run'   => 0,

            // options
            'autoskip'    => $this->getConf('autoskip'),
            'ignorelocks' => false,
            'copyacl'     => true,
            'copylinkedmedia' => false,
            'autorewrite' => $this->getConf('autorewrite'),
            'copyonly'    => true,
            'create_start_pages' => false,
            'start_page_template' => $this->getConf('start_page_template'),

            // errors
            'lasterror'   => false
        );

        // merge whatever options are saved currently
        $file = $this->files['opts'];
        if(file_exists($file)) {
            $options       = unserialize(io_readFile($file, false));
            $this->options = array_merge($this->options, $options);
        }
    }

    /**
     * Save the current options
     *
     * @return bool
     */
    protected function saveOptions() {
        return io_saveFile($this->files['opts'], serialize($this->options));
    }

    /**
     * Return the current state of an option, null for unknown options
     *
     * @param $name
     * @return mixed|null
     */
    public function getOption($name) {
        if(isset($this->options[$name])) {
            return $this->options[$name];
        }
        return null;
    }

    /**
     * Set an option
     *
     * Note, this otpion will only be set to the current instance of this helper object. It will only
     * be written to the option file once the plan gets committed
     *
     * @param $name
     * @param $value
     */
    public function setOption($name, $value) {
        $this->options[$name] = $value;
    }

    /**
     * Update and persist the automatic error-skip setting during execution.
     *
     * @param bool $enabled
     * @return bool
     */
    public function setAutoSkip($enabled) {
        $this->options['autoskip'] = (bool) $enabled;
        return $this->saveOptions();
    }

    /**
     * Returns the progress of this plan in percent
     *
     * @return float
     */
    public function getProgress() {
        $max =
            $this->options['pages_all'] +
            $this->options['media_all'];

        $remain =
            $this->options['pages_run'] +
            $this->options['media_run'];

        if($this->options['autorewrite']) {
            $max += $this->options['affpg_all'];
            $remain += $this->options['affpg_run'];
        }
        $max += isset($this->options['startpages_all']) ? $this->options['startpages_all'] : 0;
        $remain += isset($this->options['startpages_run']) ? $this->options['startpages_run'] : 0;

        if($max == 0) return 0;
        return round((($max - $remain) * 100) / $max, 2);
    }

    /** Return completed and total progress units, including link rewrites when enabled. */
    public function getProgressCounts() {
        $total = (int) $this->options['pages_all'] + (int) $this->options['media_all'];
        $remaining = (int) $this->options['pages_run'] + (int) $this->options['media_run'];
        if($this->options['autorewrite']) {
            $total += (int) $this->options['affpg_all'];
            $remaining += (int) $this->options['affpg_run'];
        }
        $total += isset($this->options['startpages_all']) ? (int) $this->options['startpages_all'] : 0;
        $remaining += isset($this->options['startpages_run']) ? (int) $this->options['startpages_run'] : 0;
        return array('completed' => max(0, $total - $remaining), 'total' => $total);
    }

    /**
     * Check if there is a move in progress currently
     *
     * @return bool
     */
    public function inProgress() {
        return (bool) $this->options['started'];
    }

    /**
     * Check if this plan has been committed, yet
     *
     * @return bool
     */
    public function isCommited() {
        return $this->options['committed'];
    }

    /**
     * Add a single page to be moved to the plan
     *
     * @param string $src
     * @param string $dst
     */
    public function addPageMove($src, $dst) {
        $this->addMove($src, $dst, self::CLASS_DOC, self::TYPE_PAGES);
    }

    /**
     * Add a single media file to be moved to the plan
     *
     * @param string $src
     * @param string $dst
     */
    public function addMediaMove($src, $dst) {
        $this->addMove($src, $dst, self::CLASS_DOC, self::TYPE_MEDIA);
    }

    /**
     * Add a page namespace to be moved to the plan
     *
     * @param string $src
     * @param string $dst
     */
    public function addPageNamespaceMove($src, $dst) {
        $this->addMove($src, $dst, self::CLASS_NS, self::TYPE_PAGES);
    }

    /**
     * Add a media namespace to be moved to the plan
     *
     * @param string $src
     * @param string $dst
     */
    public function addMediaNamespaceMove($src, $dst) {
        $this->addMove($src, $dst, self::CLASS_NS, self::TYPE_MEDIA);
    }

    /**
     * Plans the move of a namespace or document
     *
     * @param string $src   ID of the item to move
     * @param string $dst   new ID of item namespace
     * @param int    $class (self::CLASS_NS|self::CLASS_DOC)
     * @param int    $type  (PLUGIN_MOVE_TYPE_PAGE|self::TYPE_MEDIA)
     * @throws Exception
     */
    protected function addMove($src, $dst, $class = self::CLASS_NS, $type = self::TYPE_PAGES) {
        if($this->options['committed']) throw new Exception('plan is committed already, can not be added to');

        $src = cleanID($src);
        $dst = cleanID($dst);

        $this->plan[] = array(
            'src'   => $src,
            'dst'   => $dst,
            'class' => $class,
            'type'  => $type
        );
    }

    /**
     * Abort any move or plan in progress and reset the helper
     */
    public function abort() {
        foreach($this->files as $file) {
            @unlink($file);
        }
        $this->plan = array();
        $this->loadOptions();
        helper_plugin_move_rewrite::removeAllLocks();
    }

    /**
     * This locks up the plan and prepares execution
     *
     * the plan is reordered an the needed move operations are gathered and stored in the appropriate
     * list files
     *
     * @throws Exception if you try to commit a plan twice
     * @return bool true if the plan was committed
     */
    public function commit() {
        global $conf;

        if($this->options['committed']) throw new Exception('plan is committed already, can not be committed again');

        helper_plugin_move_rewrite::addLock();


        usort($this->plan, array($this, 'planSorter'));

        // get all the documents to be moved and store them in their lists
        foreach($this->plan as $move) {
            if($move['class'] == self::CLASS_DOC) {
                // these can just be added
                $this->addToDocumentList($move['src'], $move['dst'], $move['type']);
            } else {
                // here we need a list of content first, search for it
                $docs = array();
                $path = utf8_encodeFN(str_replace(':', '/', $move['src']));
                $opts = array('depth' => 0, 'skipacl' => true);
                if($move['type'] == self::TYPE_PAGES) {
                    search($docs, $conf['datadir'], 'search_allpages', $opts, $path);
                } else {
                    search($docs, $conf['mediadir'], 'search_media', $opts, $path);
                }

                // how much namespace to strip?
                if($move['src'] !== '') {
                    $strip = strlen($move['src']) + 1;
                } else {
                    $strip = 0;
                }
                if($move['dst']) $move['dst'] .= ':';

                // now add all the found documents to our lists
                foreach($docs as $doc) {
                    $from = $doc['id'];
                    $to   = $move['dst'] . substr($doc['id'], $strip);
                    $this->addToDocumentList($from, $to, $move['type']);
                }

                // remember the namespace move itself
                if($move['type'] == self::TYPE_PAGES) {
                    // FIXME we use this to move namespace subscriptions later on and for now only do it on
                    //       page namespace moves, but subscriptions work for both, but what when only one of
                    //       them is moved? Should it be copied then? Complicated. This is good enough for now
                    if(!$this->options['copyonly']) {
                        $this->addToDocumentList($move['src'], $move['dst'], self::CLASS_NS);
                    }
                }
                $this->findMissingDocuments($move['src'] . ':', $move['dst'],$move['type']);
            }
            // store what pages are affected by this move
            if(!$this->options['copyonly']) {
                $this->findAffectedPages($move['src'], $move['dst'], $move['class'], $move['type']);
            }
        }

        if($this->options['copyonly']) {
            if(!empty($this->options['copylinkedmedia'])) {
                $this->addLinkedMediaToCopyPlan();
            }

            $this->prepareStartPages();

            $copyMaps = array(
                'pages' => array_merge($this->tmpstore['miss'], $this->tmpstore['pages']),
                'media' => array_merge($this->tmpstore['miss_media'], $this->tmpstore['media']),
                'namespaces' => array(),
            );
            foreach($this->plan as $move) {
                if($move['class'] === self::CLASS_NS && $move['type'] === self::TYPE_PAGES) {
                    $copyMaps['namespaces'][$move['src'] . ':*'] = rtrim($move['dst'], ':') . ':*';
                }
            }
            io_saveFile($this->files['copymap'], serialize($copyMaps));
            $this->tmpstore['miss'] = array();
            $this->tmpstore['miss_media'] = array();
        } else {
            $this->prepareStartPages();
        }

        $this->options['impact_paths'] = $this->buildImpactPaths();

        $this->storeDocumentLists();

        if(!$this->options['pages_all'] && !$this->options['media_all']) {
            msg($this->getLang('noaction'), -1);
            return false;
        }

        $this->options['committed'] = true;
        $this->saveOptions();

        return true;
    }

    /**
     * Add media referenced by copied pages when those files are outside the selected scope.
     */
    protected function addLinkedMediaToCopyPlan() {
        $pageMaps = array_merge($this->tmpstore['miss'], $this->tmpstore['pages']);
        $mediaMaps = array_merge($this->tmpstore['miss_media'], $this->tmpstore['media']);

        foreach($pageMaps as $srcPage => $dstPage) {
            if(!page_exists($srcPage)) continue;

            $references = p_get_metadata($srcPage, 'relation media', METADATA_RENDER_UNLIMITED);
            if(!is_array($references)) continue;

            $targetNamespace = $this->getLinkedMediaTargetNamespace($srcPage, $dstPage);
            if($targetNamespace === '') $targetNamespace = 'copied_media';

            foreach(array_keys($references) as $srcMedia) {
                $srcMedia = cleanID($srcMedia);
                if(isset($mediaMaps[$srcMedia]) || !@file_exists(mediaFN($srcMedia))) continue;

                $dstMedia = cleanID($targetNamespace . ':' . $srcMedia);
                if($srcMedia === $dstMedia) continue;

                $this->addToDocumentList($srcMedia, $dstMedia, self::TYPE_MEDIA);
                $this->tmpstore['media'][$srcMedia] = $dstMedia;
                $mediaMaps[$srcMedia] = $dstMedia;
            }
        }
    }

    /**
     * Resolve the destination namespace corresponding to a copied page.
     */
    protected function getLinkedMediaTargetNamespace($srcPage, $dstPage) {
        $bestLength = -1;
        $targetNamespace = null;

        foreach($this->plan as $move) {
            if($move['type'] !== self::TYPE_PAGES) continue;

            if($move['class'] === self::CLASS_NS) {
                $srcNamespace = trim($move['src'], ':');
                if(
                    $srcNamespace !== '' &&
                    $srcPage !== $srcNamespace &&
                    strpos($srcPage, $srcNamespace . ':') !== 0
                ) continue;

                $length = strlen($srcNamespace);
                if($length > $bestLength) {
                    $bestLength = $length;
                    $targetNamespace = trim($move['dst'], ':');
                }
            } elseif($move['class'] === self::CLASS_DOC && $move['src'] === $srcPage) {
                $targetNamespace = getNS($move['dst']);
                $bestLength = PHP_INT_MAX;
            }
        }

        return $targetNamespace === null ? getNS($dstPage) : $targetNamespace;
    }

    /**
     * Execute the next steps
     *
     * @param bool $skip set to true to skip the next first step (skip error)
     * @return bool|int false on errors, otherwise the number of remaining steps
     * @throws Exception
     */
    public function nextStep($skip = false, $skipSource = 'unspecified') {
        if(!$this->options['committed']) throw new Exception('plan is not committed yet!');

        $previousError = $this->options['lasterror'];

        $this->executionStartedAt = isset($_SERVER['REQUEST_TIME_FLOAT'])
            ? (float) $_SERVER['REQUEST_TIME_FLOAT']
            : microtime(true);

        // A retry or an explicit skip resolves the previous error state.
        $this->clearError();

        // execution has started
        if(!$this->options['started']) $this->options['started'] = time();

        helper_plugin_move_rewrite::addLock();

        if(@filesize($this->files['pagelist']) > 1) {
            $todo = $this->stepThroughDocuments(self::TYPE_PAGES, $skip, $previousError, $skipSource);
            if($todo === false) return $this->storeError();
            return max($todo, 1); // force one more call
        }

        if(@filesize($this->files['medialist']) > 1) {
            $todo = $this->stepThroughDocuments(self::TYPE_MEDIA, $skip, $previousError, $skipSource);
            if($todo === false) return $this->storeError();
            return max($todo, 1); // force one more call
        }

        if(@filesize($this->files['missing']) > 1 && @filesize($this->files['affected']) > 1) {
            $todo = $this->stepThroughMissingDocuments(self::TYPE_PAGES);
            if($todo === false) return $this->storeError();
            return max($todo, 1); // force one more call
        }

        if(@filesize($this->files['missing_media']) > 1 && @filesize($this->files['affected']) > 1) {
            $todo = $this->stepThroughMissingDocuments(self::TYPE_MEDIA);
            if($todo === false)return $this->storeError();
            return max($todo, 1); // force one more call
        }

        if(@filesize($this->files['namespaces']) > 1) {
            $todo = $this->stepThroughNamespaces();
            if($todo === false) return $this->storeError();
            return max($todo, 1); // force one more call
        }

        helper_plugin_move_rewrite::removeAllLocks();

        if($this->options['autorewrite'] && @filesize($this->files['affected']) > 1) {
            $todo = $this->stepThroughAffectedPages();
            if($todo === false) return $this->storeError();
            return max($todo, 1); // force one more call
        }

        if(@filesize($this->files['startpages']) > 1) {
            $todo = $this->stepThroughStartPages($skip, $previousError, $skipSource);
            if($todo === false) return $this->storeError();
            return max($todo, 1);
        }

        if($this->options['copyonly'] && !empty($this->options['copyacl'])) {
            if(!$this->copyAclRules()) return $this->storeError();
        }

        // we're done here, clean up
        $this->abort();
        return 0;
    }

    /** Create queued start pages after moved content and link rewrites are complete. */
    protected function stepThroughStartPages($skip = false, $skipMessage = '', $skipSource = 'unspecified') {
        $file = fopen($this->files['startpages'], 'a+');
        $remaining = $this->options['startpages_run'];
        for($i = 0; $i < self::OPS_PER_RUN; $i++) {
            if($i > 0 && !$this->hasExecutionTimeRemaining()) break;
            $line = $this->getLastLine($file);
            if($line === false) break;
            list($namespace, $page) = explode("\t", trim($line), 2);

            if($skip) {
                $skip = false;
                $log = $this->buildSkippedLogLine('P', $namespace, $page, $skipMessage, $skipSource);
            } elseif(page_exists($page) || $this->namespaceHasPages($namespace)) {
                $message = sprintf($this->getLang('start_page_skipped_nonempty'), $namespace);
                $log = $this->buildSkippedLogLine('P', $namespace, $page, $message, 'namespace-not-empty');
            } elseif(auth_quickaclcheck($page) < AUTH_CREATE) {
                msg(sprintf($this->getLang('notargetperms'), $page), -1);
                if($this->options['autoskip']) {
                    $log = $this->buildAutoSkippedLogLine('P', $namespace, $page);
                } else {
                    $log = $this->build_log_line('P', $namespace, $page, false);
                    $this->write_log($log);
                    fclose($file);
                    return false;
                }
            } else {
                $title = ucfirst(str_replace('_', ' ', noNS($namespace)));
                $text = str_replace(array('~~TITLE~~', 'TITLE'), $title, $this->options['start_page_template']);
                saveWikiText($page, $text, $this->getLang('start_page_created_summary'));
                if(!page_exists($page)) {
                    msg(sprintf($this->getLang('start_page_create_failed'), $page), -1);
                    if($this->options['autoskip']) {
                        $log = $this->buildAutoSkippedLogLine('P', $namespace, $page);
                    } else {
                        $log = $this->build_log_line('P', $namespace, $page, false);
                        $this->write_log($log);
                        fclose($file);
                        return false;
                    }
                } else {
                    $log = $this->build_log_line('P', $namespace, $page, true);
                }
            }

            ftruncate($file, ftell($file));
            $remaining = --$this->options['startpages_run'];
            $this->write_log($log);
            $this->saveOptions();
        }
        fclose($file);
        return $remaining;
    }

    /**
     * Returns the list of page and media moves and the affected pages as a HTML list
     *
     * @return string
     */
    public function previewHTML() {
        $html = '';

        $html .= '<ul>';
        if(@file_exists($this->files['pagelist'])) {
            $pagelist = file($this->files['pagelist']);
            foreach($pagelist as $line) {
                list($old, $new) = explode("\t", trim($line));

                $html .= '<li class="page"><div class="li">';
                $html .= hsc($old);
                $html .= '→';
                $html .= hsc($new);
                $html .= '</div></li>';
            }
        }
        if(@file_exists($this->files['medialist'])) {
            $medialist = file($this->files['medialist']);
            foreach($medialist as $line) {
                list($old, $new) = explode("\t", trim($line));

                $html .= '<li class="media"><div class="li">';
                $html .= hsc($old);
                $html .= '→';
                $html .= hsc($new);
                $html .= '</div></li>';
            }
        }
        if(@file_exists($this->files['affected'])) {
            $medialist = file($this->files['affected']);
            foreach($medialist as $page) {
                $html .= '<li class="affected"><div class="li">';
                $html .= '↷';
                $html .= hsc($page);
                $html .= '</div></li>';
            }
        }
        $html .= '</ul>';

        return $html;
    }

    /** Return filesystem path patterns affected by the committed plan. */
    public function getImpactPaths() {
        return isset($this->options['impact_paths']) && is_array($this->options['impact_paths'])
            ? $this->options['impact_paths']
            : array();
    }

    /** Build a deduplicated list of data-directory patterns before work lists are consumed. */
    protected function buildImpactPaths() {
        global $conf;

        $paths = array();
        $copyOnly = !empty($this->options['copyonly']);
        $pageIds = array_merge($this->tmpstore['pages'], $this->tmpstore['miss']);
        $mediaIds = array_merge($this->tmpstore['media'], $this->tmpstore['miss_media']);

        foreach($pageIds as $src => $dst) {
            if(!$copyOnly) {
                $this->addImpactNamespace($paths, $conf['datadir'], $src);
                $this->addImpactNamespace($paths, $conf['metadir'], $src);
                $this->addImpactNamespace($paths, $conf['olddir'], $src);
            }
            $this->addImpactNamespace($paths, $conf['datadir'], $dst);
            $this->addImpactNamespace($paths, $conf['metadir'], $dst);
            if(!$copyOnly) $this->addImpactNamespace($paths, $conf['olddir'], $dst);
        }

        foreach($this->tmpstore['startpages'] as $namespace => $dst) {
            $this->addImpactNamespace($paths, $conf['datadir'], $dst);
            $this->addImpactNamespace($paths, $conf['metadir'], $dst);
        }

        foreach($mediaIds as $src => $dst) {
            if(!$copyOnly) {
                $this->addImpactNamespace($paths, $conf['mediadir'], $src);
                $this->addImpactNamespace($paths, $conf['mediametadir'], $src);
                $this->addImpactNamespace($paths, $conf['mediaolddir'], $src);
            }
            $this->addImpactNamespace($paths, $conf['mediadir'], $dst);
            $this->addImpactNamespace($paths, $conf['mediametadir'], $dst);
            if(!$copyOnly) $this->addImpactNamespace($paths, $conf['mediaolddir'], $dst);
        }

        if(!$copyOnly && !empty($this->options['autorewrite'])) {
            foreach($this->tmpstore['affpg'] as $page => $unused) {
                $this->addImpactNamespace($paths, $conf['datadir'], $page);
                $this->addImpactNamespace($paths, $conf['metadir'], $page);
                $this->addImpactNamespace($paths, $conf['olddir'], $page);
            }
        }

        foreach($this->plan as $move) {
            if($move['class'] !== self::CLASS_NS) continue;
            if($move['type'] === self::TYPE_MEDIA) {
                $directories = array($conf['mediadir'], $conf['mediametadir']);
                if(!$copyOnly) $directories[] = $conf['mediaolddir'];
            } else {
                $directories = array($conf['datadir'], $conf['metadir']);
                if(!$copyOnly) $directories[] = $conf['olddir'];
            }
            foreach($directories as $directory) {
                if(!$copyOnly) $this->addImpactDirectory($paths, $directory, $move['src']);
                $this->addImpactDirectory($paths, $directory, $move['dst']);
            }
        }

        if($pageIds || $mediaIds || $this->tmpstore['startpages']) $this->addImpactDirectory($paths, $conf['indexdir'], '');
        if($copyOnly && !empty($this->options['copyacl'])) {
            global $config_cascade;
            if(!empty($config_cascade['acl']['default'])) {
                $acl = $config_cascade['acl']['default'];
                $paths[$this->relativeImpactPath($acl)] = true;
            }
        }

        return $this->factorImpactPaths(array_keys($paths));
    }

    /** Remove descendant wildcard paths already covered by an ancestor directory. */
    protected function factorImpactPaths($paths) {
        $wildcards = array();
        $exact = array();
        foreach($paths as $path) {
            if(substr($path, -2) === '/*') $wildcards[] = $path;
            else $exact[] = $path;
        }

        usort($wildcards, function($a, $b) {
            $length = strlen($a) - strlen($b);
            return $length === 0 ? strcmp($a, $b) : $length;
        });

        $kept = array();
        $coveredPrefixes = array();
        foreach($wildcards as $path) {
            $prefix = substr($path, 0, -1);
            $covered = false;
            foreach($coveredPrefixes as $parentPrefix) {
                if(strpos($prefix, $parentPrefix) === 0) {
                    $covered = true;
                    break;
                }
            }
            if($covered) continue;
            $kept[] = $path;
            $coveredPrefixes[] = $prefix;
        }

        $paths = array_merge($exact, $kept);
        sort($paths, SORT_STRING);
        return $paths;
    }

    /** Queue an index page for destination namespaces without an incoming page of their own. */
    protected function prepareStartPages() {
        if(empty($this->options['create_start_pages'])) return;
        global $conf;

        $startPageName = !empty($conf['start']) ? $conf['start'] : 'start';
        $candidateNamespaces = array();
        $pageNamespaces = array();
        foreach(array($this->tmpstore['pages'], $this->tmpstore['miss']) as $pageMoves) {
            foreach($pageMoves as $dst) {
                $namespace = getNS($dst);
                if($namespace !== false && $namespace !== '') $pageNamespaces[$namespace] = true;
            }
        }

        foreach(array($this->tmpstore['pages'], $this->tmpstore['miss'], $this->tmpstore['media'], $this->tmpstore['miss_media']) as $moves) {
            foreach($moves as $dst) {
                $namespace = getNS($dst);
                while($namespace !== false && $namespace !== '') {
                    $candidateNamespaces[$namespace] = true;
                    $separator = strrpos($namespace, ':');
                    $namespace = $separator === false ? '' : substr($namespace, 0, $separator);
                }
            }
        }

        foreach($candidateNamespaces as $namespace => $unused) {
            if(isset($pageNamespaces[$namespace])) continue;
            $page = $namespace . ':' . $startPageName;
            $this->tmpstore['startpages'][$namespace] = $page;
        }
    }

    /** Check page files directly in a namespace without relying on the search index. */
    protected function namespaceHasPages($namespace) {
        global $conf;

        $directory = rtrim($conf['datadir'], '/') . '/' . utf8_encodeFN(str_replace(':', '/', $namespace));
        if(!is_dir($directory)) return false;
        foreach(glob($directory . '/*.txt') as $pageFile) {
            if(is_file($pageFile)) return true;
        }
        return false;
    }

    /** Add the directory containing an ID and its matching files to the impact list. */
    protected function addImpactNamespace(&$paths, $directory, $id) {
        $namespace = getNS($id);
        $this->addImpactDirectory($paths, $directory, $namespace === false ? '' : $namespace);
    }

    /** Add a display-safe relative directory wildcard to the impact list. */
    protected function addImpactDirectory(&$paths, $directory, $namespace) {
        $namespace = trim((string) $namespace, ':');
        $relative = $namespace === '' ? '*' : utf8_encodeFN(str_replace(':', '/', $namespace)) . '/*';
        $paths[$this->relativeImpactPath(rtrim($directory, '/') . '/' . $relative)] = true;
    }

    /** Convert an absolute configured path to a path relative to the wiki root when possible. */
    protected function relativeImpactPath($path) {
        $root = rtrim(DOKU_INC, '/');
        if(strpos($path, $root . '/') === 0) return substr($path, strlen($root) + 1);
        return $path;
    }

    /**
     * Render move operations with their current status.
     *
     * @return string
     */
    public function logHTML() {
        $entries = array();

        if($this->logFile && is_readable($this->logFile)) {
            foreach(file($this->logFile, FILE_IGNORE_NEW_LINES) as $line) {
                $parts = explode("\t", $line, 7);
                if(count($parts) < 6) continue;
                $key = $parts[2] . "\t" . $parts[3] . "\t" . $parts[4];
                $message = isset($parts[6]) ? $parts[6] : '';
                if(
                    $parts[5] === 'skipped' && $message === '' && isset($entries[$key]) &&
                    $entries[$key]['status'] === 'failed'
                ) {
                    $message = $entries[$key]['message'];
                }
                if($parts[5] === 'skipped' && $message === '') {
                    $reason = $parts[2] === 'M'
                        ? $this->MoveOperator->getMediaBlockReason($parts[3], $parts[4])
                        : $this->MoveOperator->getPageBlockReason($parts[3], $parts[4]);
                    $message = $reason
                        ? sprintf($this->getLang('log_skipped_detected_blocker'), $reason)
                        : $this->getLang('log_skipped_no_blocker');
                }
                $entries[$key] = array(
                    'type' => $parts[2],
                    'src' => $parts[3],
                    'dst' => $parts[4],
                    'status' => $parts[5],
                    'message' => $message,
                );
            }
        }

        foreach(array(array('P', 'pagelist'), array('M', 'medialist'), array('P', 'startpages')) as $listData) {
            list($type, $list) = $listData;
            if(!is_readable($this->files[$list])) continue;
            foreach(file($this->files[$list], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $parts = explode("\t", $line, 2);
                if(count($parts) !== 2) continue;
                $key = $type . "\t" . $parts[0] . "\t" . $parts[1];
                if(!isset($entries[$key])) {
                    $entries[$key] = array(
                        'type' => $type,
                        'src' => $parts[0],
                        'dst' => $parts[1],
                        'status' => 'pending',
                        'message' => '',
                    );
                }
            }
        }

        if(!$entries) return '<p class="move-log-empty">' . hsc($this->getLang('log_empty')) . '</p>';

        $labels = array(
            'success' => $this->getLang('log_done'),
            'copied' => $this->getLang('log_copied'),
            'failed' => $this->getLang('log_error'),
            'warning' => $this->getLang('log_warning'),
            'skipped' => $this->getLang('log_skipped'),
            'pending' => $this->getLang('log_pending'),
        );
        $html = '<div class="move-log-list">';
        foreach($entries as $entry) {
            $status = isset($labels[$entry['status']]) ? $entry['status'] : 'pending';
            $label = $labels[$status];
            $kind = $entry['type'] === 'M' ? $this->getLang('move_media') : $this->getLang('move_pages');
            $html .= '<details class="move-log-entry move-log-' . hsc($status) . '">';
            $html .= '<summary><span class="move-log-status">' . hsc($label) . '</span> ' . hsc($entry['src']) . '</summary>';
            $html .= '<div class="move-log-details"><p>' . hsc($kind) . ': ' . hsc($entry['src']) . ' → ' . hsc($entry['dst']) . '</p>';
            if($entry['message'] !== '') $html .= '<p class="move-log-message">' . hsc($entry['message']) . '</p>';
            $html .= '</div></details>';
        }
        $html .= '</div>';
        return $html;
    }

    /** Count final item statuses for the completion summary. */
    public function getLogSummary() {
        $summary = array(
            'moved' => 0,
            'copied' => 0,
            'failed' => 0,
            'skipped' => 0,
            'warning' => 0,
        );
        if(!$this->logFile || !is_readable($this->logFile)) return $summary;

        $entries = array();
        foreach(file($this->logFile, FILE_IGNORE_NEW_LINES) as $line) {
            $parts = explode("\t", $line, 7);
            if(count($parts) < 6) continue;
            $key = $parts[2] . "\t" . $parts[3] . "\t" . $parts[4];
            $entries[$key] = $parts[5];
        }
        foreach($entries as $status) {
            if($status === 'success' || $status === 'warning') $summary['moved']++;
            if(isset($summary[$status])) $summary[$status]++;
        }
        return $summary;
    }

    /**
     * Step through the next bunch of pages or media files
     *
     * @param int  $type (self::TYPE_PAGES|self::TYPE_MEDIA)
     * @param bool $skip should the first item be skipped?
     * @return bool|int false on error, otherwise the number of remaining documents
     */
    protected function stepThroughDocuments($type = self::TYPE_PAGES, $skip = false, $skipMessage = '', $skipSource = 'unspecified') {

        if($type == self::TYPE_PAGES) {
            $file    = $this->files['pagelist'];
            $mark    = 'P';
            $call    = $this->options['copyonly'] ? 'copyPage' : 'movePage';
            $items_run_counter = 'pages_run';
        } else {
            $file    = $this->files['medialist'];
            $mark    = 'M';
            $call    = $this->options['copyonly'] ? 'copyMedia' : 'moveMedia';
            $items_run_counter = 'media_run';
        }

        $doclist = fopen($file, 'a+');

        if($this->options['copyonly']) {
            $copyMaps = $this->getCopyMaps();
            $this->MoveOperator->setCopyMaps($copyMaps['pages'], $copyMaps['media']);
        }
        $this->MoveOperator->setIgnoreLocks(!empty($this->options['ignorelocks']));

        for($i = 0; $i < helper_plugin_move_plan::OPS_PER_RUN; $i++) {
            if($i > 0 && !$this->hasExecutionTimeRemaining()) break;
            $log = "";
            $line = $this->getLastLine($doclist);
            if($line === false) {
                break;
            }
            list($src, $dst) = explode("\t", trim($line));

            // should this item be skipped?
            if($skip === true) {
                $skip = false;
                if(trim((string) $skipMessage) === '') {
                    $skipMessage = $type == self::TYPE_MEDIA
                        ? $this->MoveOperator->getMediaBlockReason($src, $dst)
                        : $this->MoveOperator->getPageBlockReason($src, $dst);
                    if(!$skipMessage) $skipMessage = $this->getLang('log_skipped_no_blocker');
                }
                $log .= $this->buildSkippedLogLine($mark, $src, $dst, $skipMessage, $skipSource);
            } else {
            // move the page
                if(!$this->MoveOperator->$call($src, $dst)) {
                    // automatically skip this item only if wanted...
                    if(!$this->options['autoskip']) {
                        $log .= $this->build_log_line($mark, $src, $dst, false); // FAILURE!
                        // ...otherwise abort the operation
                        $this->write_log($log);
                        fclose($doclist);
                        $return_items_run = false;
                        break;
                    } else {
                        $log .= $this->buildAutoSkippedLogLine($mark, $src, $dst);
                    }
                } else {
                    $warning = $this->MoveOperator->getLastWarning();
                    if($this->options['copyonly']) {
                        $log .= $this->buildCopyLogLine($mark, $src, $dst);
                    } elseif($warning) {
                        $log .= $this->buildWarningLogLine($mark, $src, $dst, $warning);
                    } else {
                        $log .= $this->build_log_line($mark, $src, $dst, true); // SUCCESS!
                    }
                }
            }

            /*
             * This adjusts counters and truncates the document list correctly
             * It is used to finalize a successful or skipped move
             */

            ftruncate($doclist, ftell($doclist));
            $this->options[$items_run_counter]--;
            $return_items_run = $this->options[$items_run_counter];
            $this->write_log($log);
            $this->saveOptions();
        }

        if ($return_items_run !== false) {
            fclose($doclist);
        }
        return $return_items_run;
    }

    /**
     * Step through the next bunch of pages that need link corrections
     *
     * @return bool|int false on error, otherwise the number of remaining documents
     */
    protected function stepThroughAffectedPages() {
        /** @var helper_plugin_move_rewrite $Rewriter */
        $Rewriter = plugin_load('helper', 'move_rewrite');

        // handle affected pages
        $doclist = fopen($this->files['affected'], 'a+');
        for($i = 0; $i < helper_plugin_move_plan::OPS_PER_RUN; $i++) {
            if($i > 0 && !$this->hasExecutionTimeRemaining()) break;
            $page = $this->getLastLine($doclist);
            if($page === false) break;

            // rewrite it
            $Rewriter->rewritePage($page);

            // update the list file
            ftruncate($doclist, ftell($doclist));
            $this->options['affpg_run']--;
            $this->saveOptions();
        }

        fclose($doclist);
        return $this->options['affpg_run'];
    }

    /**
     * Check whether another page operation fits within the configured PHP time budget.
     * The first item in a request is always allowed so the move can make progress.
     *
     * @return bool
     */
    protected function hasExecutionTimeRemaining() {
        $maxExecutionTime = (int) ini_get('max_execution_time');
        if($maxExecutionTime <= 0) return true;

        $percentage = (float) $this->getConf('executionbudget');
        $percentage = max(1, min(100, $percentage));
        $budget = $maxExecutionTime * ($percentage / 100);

        return (microtime(true) - $this->executionStartedAt) < $budget;
    }

    /**
     * Load the complete mapping so links inside copied pages target their copied peers.
     *
     * @return array
     */
    protected function getCopyMaps() {
        if(!is_readable($this->files['copymap'])) return array('pages' => array(), 'media' => array());
        $maps = @unserialize(io_readFile($this->files['copymap'], false));
        if(!is_array($maps)) return array('pages' => array(), 'media' => array());
        return array(
            'pages' => isset($maps['pages']) && is_array($maps['pages']) ? $maps['pages'] : array(),
            'media' => isset($maps['media']) && is_array($maps['media']) ? $maps['media'] : array(),
        );
    }

    /** Copy explicit ACL entries for copied pages and namespaces to their new IDs. */
    protected function copyAclRules() {
        global $AUTH_ACL, $config_cascade;

        if(empty($config_cascade['acl']['default']) || !is_writable($config_cascade['acl']['default'])) {
            msg($this->getLang('copyacl_failed'), -1);
            return false;
        }

        $maps = $this->getCopyMaps();
        $scopeMap = array_merge($maps['pages'], isset($maps['namespaces']) ? $maps['namespaces'] : array());
        uksort($scopeMap, function($a, $b) { return strlen($b) - strlen($a); });

        $lines = is_array($AUTH_ACL) ? $AUTH_ACL : file($config_cascade['acl']['default']);
        if(!is_array($lines)) {
            msg($this->getLang('copyacl_failed'), -1);
            return false;
        }

        $existing = array();
        foreach($lines as $line) {
            if(preg_match('/^([^\s#]+)[ \t]+([^\s#]+)[ \t]+(\d+)/', $line, $match)) {
                $existing[$match[1] . "\t" . $match[2]] = true;
            }
        }

        $additions = array();
        foreach($lines as $line) {
            if(!preg_match('/^([^\s#]+)([ \t]+[^\s#]+[ \t]+\d+.*)$/', $line, $match)) continue;
            $sourceScope = $match[1];
            $targetScope = false;
            foreach($scopeMap as $src => $dst) {
                if(substr($src, -2) === ':*') {
                    $prefix = substr($src, 0, -1);
                    if(strpos($sourceScope, $prefix) === 0) {
                        $targetScope = substr($dst, 0, -1) . substr($sourceScope, strlen($prefix));
                        break;
                    }
                } elseif($sourceScope === $src) {
                    $targetScope = $dst;
                    break;
                }
            }
            if($targetScope === false || $targetScope === $sourceScope) continue;
            $rule = rtrim($targetScope . $match[2], "\r\n") . "\n";
            preg_match('/^[^\s]+[ \t]+([^\s]+)/', $rule, $principal);
            if(isset($principal[1]) && isset($existing[$targetScope . "\t" . $principal[1]])) continue;
            $additions[] = $rule;
            if(isset($principal[1])) $existing[$targetScope . "\t" . $principal[1]] = true;
        }

        if(!$additions) return true;
        if(!io_saveFile($config_cascade['acl']['default'], implode('', $additions), true)) {
            msg($this->getLang('copyacl_failed'), -1);
            return false;
        }
        $AUTH_ACL = file($config_cascade['acl']['default']);
        return true;
    }

    /**
     * Step through all the links to missing pages that should be moved
     *
     * This simply adds the moved missing pages to all affected pages meta data. This will add
     * the meta data to pages not linking to the affected pages but this should still be faster
     * than figuring out which pages need this info.
     *
     * This does not step currently, but handles all pages in one step.
     *
     * @param int $type
     *
     * @return int always 0
     * @throws Exception
     */
    protected function stepThroughMissingDocuments($type = self::TYPE_PAGES) {
        if($type != self::TYPE_PAGES && $type != self::TYPE_MEDIA) {
            throw new Exception('wrong type specified');
        }
        /** @var helper_plugin_move_rewrite $Rewriter */
        $Rewriter = plugin_load('helper', 'move_rewrite');

        $miss = array();
        if ($type == self::TYPE_PAGES) {
            $missing_fn = $this->files['missing'];
        } else {
            $missing_fn = $this->files['missing_media'];
        }
        $missing = file($missing_fn);
        foreach($missing as $line) {
            $line = trim($line);
            if($line == '') continue;
            list($src, $dst) = explode("\t", $line);
            $miss[$src] = $dst;
        }

        $affected = file($this->files['affected']);
        foreach($affected as $page){
            $page = trim($page);

            if ($type == self::TYPE_PAGES) {
                $Rewriter->setMoveMetas($page, $miss, 'pages');
            } else {
                $Rewriter->setMoveMetas($page, $miss, 'media');
            }
        }

        unlink($missing_fn);
        return 0;
    }

    /**
     * Step through all the namespace moves
     *
     * This does not step currently, but handles all namespaces in one step.
     *
     * Currently moves namespace subscriptions only.
     *
     * @return int always 0
     * @todo maybe add an event so plugins can move more stuff?
     * @todo fixed that $src and $dst are seperated by tab, not newline. This method has no tests?
     */
    protected function stepThroughNamespaces() {
        /** @var helper_plugin_move_file $FileMover */
        $FileMover = plugin_load('helper', 'move_file');

        $lines = io_readFile($this->files['namespaces']);
        $lines = explode("\n", $lines);

        foreach($lines as $line) {
            // There is an empty line at the end of the list.
            if ($line === '') continue;

            list($src, $dst) = explode("\t", trim($line));
            $FileMover->moveNamespaceSubscription($src, $dst);
        }

        @unlink($this->files['namespaces']);
        return 0;
    }

    /**
     * Retrieve the last error from the MSG array and store it in the options
     *
     * @todo rebuild error handling based on exceptions
     *
     * @return bool always false
     */
    protected function storeError() {
        global $MSG;

        if(is_array($MSG) && count($MSG)) {
            $last                       = array_shift($MSG);
            $this->options['lasterror'] = $last['msg'];
            unset($GLOBALS['MSG']);
        } else {
            $this->options['lasterror'] = 'Unknown error';
        }
        $this->saveOptions();

        return false;
    }

    /**
     * Reset the error state
     */
    protected function clearError() {
        $this->options['lasterror'] = false;
        $this->saveOptions();
    }

    /**
     * Get the last error message or false if no error occured
     *
     * @return bool|string
     */
    public function getLastError() {
        return $this->options['lasterror'];
    }

    /**
     * Appends a page move operation in the list file
     *
     * If the src has been added before, this is ignored. This makes sure you can move a single page
     * out of a namespace first, then move the namespace somewhere else.
     *
     * @param string $src
     * @param string $dst
     * @param int    $type
     * @throws Exception
     */
    protected function addToDocumentList($src, $dst, $type = self::TYPE_PAGES) {
        if($type == self::TYPE_PAGES) {
            $store = 'pages';
        } else if($type == self::TYPE_MEDIA) {
            $store = 'media';
        } else if($type == self::CLASS_NS) {
            $store = 'ns';
        } else {
            throw new Exception('Unknown type ' . $type);
        }

        if(!isset($this->tmpstore[$store][$src])) {
            $this->tmpstore[$store][$src] = $dst;
        }
    }

    /**
     * Add the list of pages to the list of affected pages whose links need adjustment
     *
     * @param string|array $pages
     */
    protected function addToAffectedPagesList($pages) {
        if(!is_array($pages)) $pages = array($pages);

        foreach($pages as $page) {
            if(!isset($this->tmpstore['affpg'][$page])) {
                $this->tmpstore['affpg'][$page] = true;
            }
        }
    }

    /**
     * Looks up pages that will be affected by a move of $src
     *
     * Calls addToAffectedPagesList() directly to store the result
     *
     * @param string $src source namespace
     * @param string $dst destination namespace
     * @param int    $class
     * @param int    $type
     */
    protected function findAffectedPages($src, $dst, $class, $type) {
        $idx = idx_get_indexer();

        if($class == self::CLASS_NS) {
            $src_ = "$src:*"; // use wildcard lookup for namespaces
        } else {
            $src_ = $src;
        }

        $pages = array();
        if($type == self::TYPE_PAGES) {
            $pages = $idx->lookupKey('relation_references', $src_);
            $len = strlen($src);
            foreach($pages as &$page) {
                if (substr($page, 0, $len + 1) === "$src:") {
                    $page = $dst . substr($page, $len + 1);
                }
            }
            unset($page);
        } else if($type == self::TYPE_MEDIA) {
            $pages = $idx->lookupKey('relation_media', $src_);
        }

        $this->addToAffectedPagesList($pages);
    }

    /**
     * Find missing pages in the $src namespace
     *
     * @param string $src source namespace
     * @param string $dst destination namespace
     * @param int    $type either self::TYPE_PAGES or self::TYPE_MEDIA
     */
    protected function findMissingDocuments($src, $dst, $type = self::TYPE_PAGES) {
        global $conf;

        // FIXME this duplicates Doku_Indexer::getIndex()
        if ($type == self::TYPE_PAGES) {
            $fn = $conf['indexdir'] . '/relation_references_w.idx';
        } else {
            $fn = $conf['indexdir'] . '/relation_media_w.idx';
        }
        if (!@file_exists($fn)){
            $referenceidx = array();
        } else {
            $referenceidx = file($fn, FILE_IGNORE_NEW_LINES);
        }

        $len = strlen($src);
        foreach($referenceidx as $idx => $page) {
            if(substr($page, 0, $len) != "$src") continue;

            // remember missing pages
            if ($type == self::TYPE_PAGES) {
                if(!page_exists($page)) {
                    $newpage = $dst . substr($page, $len);
                    $this->tmpstore['miss'][$page] = $newpage;
                }
            } else {
                if(!file_exists(mediaFN($page))){
                    $newpage = $dst . substr($page, $len);
                    $this->tmpstore['miss_media'][$page] = $newpage;
                }
            }
        }
    }

    /**
     * Store the aggregated document lists in the file system and reset the internal storage
     *
     * @throws Exception
     */
    protected function storeDocumentLists() {
        $lists = array(
            'pages' => $this->files['pagelist'],
            'media' => $this->files['medialist'],
            'ns'    => $this->files['namespaces'],
            'affpg' => $this->files['affected'],
            'miss'  => $this->files['missing'],
            'miss_media'  => $this->files['missing_media'],
            'startpages' => $this->files['startpages'],
        );

        foreach($lists as $store => $file) {
            // anything to do?
            $count = count($this->tmpstore[$store]);
            if(!$count) continue;

            // prepare and save content
            $data                   = '';
            $this->tmpstore[$store] = array_reverse($this->tmpstore[$store]); // store in reverse order
            foreach($this->tmpstore[$store] as $src => $dst) {
                if($dst === true) {
                    $data .= "$src\n"; // for affected pages only one ID is saved
                } else {
                    $data .= "$src\t$dst\n";
                }

            }
            io_saveFile($file, $data);

            // set counters
            if($store != 'ns') {
                $this->options[$store . '_all'] = $count;
                $this->options[$store . '_run'] = $count;
            }

            // reset the list
            $this->tmpstore[$store] = array();
        }
    }

    /**
     * Get the last line from the list that is stored in the file that is referenced by the handle
     * The handle is set to the newline before the file id
     *
     * @param resource $handle The file handle to read from
     * @return string|bool the last id from the list or false if there is none
     */
    protected function getLastLine($handle) {
        // begin the seek at the end of the file
        fseek($handle, 0, SEEK_END);
        $line = '';

        // seek one backwards as long as it's possible
        while(fseek($handle, -1, SEEK_CUR) >= 0) {
            $c = fgetc($handle);
            if($c === false) return false; // EOF, i.e. the file is empty
            fseek($handle, -1, SEEK_CUR); // reset the position to the character that was read

            if($c == "\n") {
                if($line === '') {
                    continue; // this line was empty, continue
                } else {
                    break; // we have a line, finish
                }
            }

            $line = $c . $line; // prepend char to line
        }

        if($line === '') return false; // beginning of file reached and no content

        return $line;
    }

    /**
     * Callback for usort to sort the move plan
     *
     * @param $a
     * @param $b
     * @return int
     */
    public function planSorter($a, $b) {
        // do page moves before namespace moves
        if($a['class'] == self::CLASS_DOC && $b['class'] == self::CLASS_NS) {
            return -1;
        }
        if($a['class'] == self::CLASS_NS && $b['class'] == self::CLASS_DOC) {
            return 1;
        }

        // do pages before media
        if($a['type'] == self::TYPE_PAGES && $b['type'] == self::TYPE_MEDIA) {
            return -1;
        }
        if($a['type'] == self::TYPE_MEDIA && $b['type'] == self::TYPE_PAGES) {
            return 1;
        }

        // from here on we compare only apples to apples
        // we sort by depth of namespace, deepest namespaces first

        $alen = substr_count($a['src'], ':');
        $blen = substr_count($b['src'], ':');

        if($alen > $blen) {
            return -1;
        } elseif($alen < $blen) {
            return 1;
        }
        return 0;
    }

    /**
     * Create line to log result of an operation
     *
     * @param string $type
     * @param string $from
     * @param string $to
     * @param bool   $success
     *
     * @return string
     *
     * @author Andreas Gohr <gohr@cosmocode.de>
     * @author Michael Große <grosse@cosmocode.de>
     */
    public function build_log_line ($type, $from, $to, $success) {
        global $MSG;

        $now = time();
        $date   = date('Y-m-d H:i:s', $now); // for human readability
        if($success) {
            $ok  = 'success';
            $msg = '';
        } else {
            $ok  = 'failed';
            $msg = is_array($MSG) && count($MSG)
                ? $MSG[count($MSG) - 1]['msg']
                : $this->getLang('log_error');
            $msg = str_replace(array("\r", "\n", "\t"), ' ', $msg);
        }

        $log = "$now\t$date\t$type\t$from\t$to\t$ok\t$msg\n";
        return $log;
    }

    /**
     * Create a log entry for an item explicitly skipped by the user.
     *
     * @param string $type
     * @param string $from
     * @param string $to
     * @return string
     */
    protected function buildSkippedLogLine($type, $from, $to, $message = '', $source = 'unspecified') {
        $now = time();
        $date = date('Y-m-d H:i:s', $now);
        if(trim((string) $message) === '') {
            $message = $this->getLang('log_skipped_no_blocker');
        } elseif($source === 'skip') {
            $message = sprintf($this->getLang('log_skipped_after_error'), $message);
        }
        if($source !== 'namespace-not-empty') {
            $message = sprintf($this->getLang('log_skipped_source'), $source, $message);
        }
        $message = str_replace(array("\r", "\n", "\t"), ' ', $message);
        return "$now\t$date\t$type\t$from\t$to\tskipped\t$message\n";
    }

    /** Record a failed item skipped by the automatic error handling option. */
    protected function buildAutoSkippedLogLine($type, $from, $to) {
        global $MSG;

        $message = is_array($MSG) && count($MSG)
            ? $MSG[count($MSG) - 1]['msg']
            : $this->getLang('log_error');
        $message = str_replace(array("\r", "\n", "\t"), ' ', $message);
        $message = sprintf($this->getLang('log_error_skipped'), $message);

        $now = time();
        $date = date('Y-m-d H:i:s', $now);
        return "$now\t$date\t$type\t$from\t$to\tskipped\t$message\n";
    }

    /**
     * Create a log entry for a successful move with an index warning.
     *
     * @param string $type
     * @param string $from
     * @param string $to
     * @param string $warning
     * @return string
     */
    protected function buildWarningLogLine($type, $from, $to, $warning) {
        $now = time();
        $date = date('Y-m-d H:i:s', $now);
        return "$now\t$date\t$type\t$from\t$to\twarning\t$warning\n";
    }

    /**
     * Create a log entry for an item copied instead of moved.
     *
     * @param string $type
     * @param string $from
     * @param string $to
     * @return string
     */
    protected function buildCopyLogLine($type, $from, $to) {
        $now = time();
        $date = date('Y-m-d H:i:s', $now);
        return "$now\t$date\t$type\t$from\t$to\tcopied\t\n";
    }

    /**
     * write log to file
     *
     * @param $log
     */
    protected function write_log ($log) {
        global $conf;
        $optime = $this->options['started'];
        $this->logFile = $this->getLogFile($optime);
        io_saveFile($this->logFile, $log, true);
    }

    /**
     * Get the log filename for a move operation.
     *
     * @param int $timestamp
     * @return string
     */
    protected function getLogFile($timestamp) {
        global $conf;
        return $conf['cachedir'] . '/move/' . dformat($timestamp, '%Y%m%d-%H%M%S') . '.log';
    }

}
