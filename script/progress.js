jQuery('#plugin_move__progress').each(function () {
    var $this = jQuery(this);

    // initialize the progress bar
    var $progressbar = $this.find('.progress');
    $progressbar.html('');
    $progressbar.progressbar({
        value: $progressbar.data('progress')
    });
    var $progressLabel = jQuery('<span class="move-progress-label" aria-live="polite">');
    $progressbar.append($progressLabel);
    var updateProgressLabel = function(progress, counts) {
        counts = counts || {};
        var percent = Math.round((parseFloat(progress) || 0) * 100) / 100;
        var label = LANG.plugins.move.progress_count
            .replace('%1$s', counts.completed || 0)
            .replace('%2$s', counts.total || 0)
            .replace('%3$s', percent);
        $progressLabel.text(label);
    };
    updateProgressLabel($progressbar.data('progress'), {
        completed: $progressbar.data('completed'),
        total: $progressbar.data('total')
    });

    /**
     * Set visibility of buttons according to current error state
     *
     * @param isError
     */
    var setButtons = function(isError) {
        $this.find('.ctlfrm-start').addClass('hide');

        if(isError) {
            $this.find('.ctlfrm-skip').removeClass('hide');
            $this.find('.ctlfrm-retry').removeClass('hide');
            $this.find('.ctlfrm-continue').addClass('hide');
        }else {
            $this.find('.ctlfrm-skip').addClass('hide');
            $this.find('.ctlfrm-retry').addClass('hide');
            $this.find('.ctlfrm-continue').addClass('hide');
        }
    };

    /**
     * Execute the next steps
     *
     * @param {bool} skip should an error be skipped?
     */
    var nextStep = function(skip, skipSource) {
        // clear error output
        $this.find('.output').html('');
        $this.find('.output').removeClass('has-error');

        $this.find('.controls img').removeClass('hide');
        setButtons(false);

        // execute AJAX
        jQuery.post(
            DOKU_BASE + 'lib/exe/ajax.php',
            {
                call: 'plugin_move_progress',
                skip: (skip === true || skip === '1') ? 1 : 0,
                skip_source: skipSource || 'continue',
                autoskip: $this.find('.move-autoskip-toggle').is(':checked') ? 1 : 0
            },
            function (data) {
                $progressbar.progressbar('option', 'value', data.progress);
                updateProgressLabel(data.progress, data.progress_counts);
                $this.find('.controls img').addClass('hide');
                $this.find('.move-log-content').html(data.log);

                if (data.error) {
                    $this.find('.output').html('<p><div class="error">' + data.error + '</div></p>').addClass('has-error');
                    if($this.find('.move-autoskip-toggle').is(':checked')) {
                        nextStep(true, 'autoskip-after-error');
                    } else {
                        setButtons(true);
                    }
                } else if (data.complete) {
                    $progressbar.progressbar('option', 'value', 100);
                    updateProgressLabel(100, data.progress_counts);
                    $this.find('.output').text(LANG.plugins.move.complete);
                    $this.find('h1').first().text(LANG.plugins.move.completed_title);
                    $this.addClass('move-complete');
                    $this.find('.controls form').addClass('hide');
                    var summary = data.summary || {};
                    var $summary = jQuery('<section class="move-completion-summary" aria-live="polite">');
                    $summary.append(jQuery('<h2>').text(LANG.plugins.move.summary_title));
                    [
                        ['moved', 'summary_moved'],
                        ['copied', 'summary_copied'],
                        ['failed', 'summary_failed'],
                        ['skipped', 'summary_skipped'],
                        ['warning', 'summary_warning']
                    ].forEach(function(item) {
                        $summary.append(jQuery('<span class="move-summary-count move-summary-' + item[0] + '">')
                            .text((summary[item[0]] || 0) + ' ' + LANG.plugins.move[item[1]]));
                    });
                    $this.find('.progress').after($summary);
                    var duration = Math.max(0, parseInt(data.duration, 10) || 0);
                    var minutes = Math.floor(duration / 60);
                    var seconds = duration % 60;
                    var durationMessage = LANG.plugins.move.duration
                        .replace('%1$s', minutes)
                        .replace('%2$s', seconds);
                    $this.append(jQuery('<p class="move-completion-duration">').text(durationMessage));
                    var $impactSection = jQuery('<section class="move-impact-summary">');
                    $impactSection.append(jQuery('<h2>').text(LANG.plugins.move.impacts_title));
                    var $impactList = jQuery('<ul>');
                    (data.impacts || []).forEach(function(path) {
                        $impactList.append(jQuery('<li>').text(path));
                    });
                    if(data.impacts && data.impacts.length) {
                        $impactSection.append($impactList);
                        $this.append($impactSection);
                    }
                    showConfetti();
                } else {
                    // do it again
                    // A skip command applies to exactly one item, never later AJAX requests.
                    nextStep(false, 'continue');
                }
            }
        );
    };

    var showConfetti = function() {
        if(window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        var $confetti = jQuery('<div class="move-confetti" aria-hidden="true"></div>');
        jQuery('body').append($confetti);
        var batch = function() {
            for(var i = 0; i < 90; i++) {
                var $piece = jQuery('<span class="move-confetti-piece"></span>');
                $piece.css({
                    left: (Math.random() * 100) + '%',
                    animationDelay: (Math.random() * 0.5) + 's',
                    animationDuration: (4.5 + Math.random() * 2) + 's',
                    '--move-drift': ((Math.random() - 0.5) * 320) + 'px',
                    '--move-spin': ((Math.random() * 1440) - 720) + 'deg'
                });
                $confetti.append($piece);
            }
        };
        batch();
        var interval = window.setInterval(batch, 900);
        window.setTimeout(function() { window.clearInterval(interval); }, 10500);
        window.setTimeout(function() { $confetti.remove(); }, 14000);
    };



    // attach AJAX actions to buttons
    $this.find('.ctl-continue').click(function (e) {
        e.preventDefault();

        // move in progress, no more preview
        jQuery('#plugin_move__preview').remove();

        // should the next error be skipped?
        var form = jQuery(this).closest('form');
        var skip = form.find('[name="skip"]').val();
        var skipSource = form.find('[name="ctl"]').val() || 'continue';

        // step on it
        nextStep(skip, skipSource);
    });

    $this.find('.move-autoskip-toggle').change(function() {
        if(this.checked && $this.find('.output').hasClass('has-error')) {
            nextStep(true, 'autoskip-checkbox-enabled-after-error');
        }
    });

});


// hide preview list on namespace move
jQuery('#plugin_move__preview').each(function () {
    var $this = jQuery(this);
    $this.find('ul').hide();
    $this.find('span')
        .click(function () {
            $this.find('ul').dw_toggle();
            $this.find('span').toggleClass('closed');
        })
        .addClass('closed');
});
