jQuery('#plugin_move__progress').each(function () {
    var $this = jQuery(this);

    // initialize the progress bar
    var $progressbar = $this.find('.progress');
    $progressbar.html('');
    $progressbar.progressbar({
        value: $progressbar.data('progress')
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
    var nextStep = function(skip) {
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
                skip: skip === true || skip === '1',
                autoskip: $this.find('.move-autoskip-toggle').is(':checked')
            },
            function (data) {
                $progressbar.progressbar('option', 'value', data.progress);
                $this.find('.controls img').addClass('hide');
                $this.find('.move-log-content').html(data.log);

                if (data.error) {
                    $this.find('.output').html('<p><div class="error">' + data.error + '</div></p>').addClass('has-error');
                    if($this.find('.move-autoskip-toggle').is(':checked')) {
                        nextStep(true);
                    } else {
                        setButtons(true);
                    }
                } else if (data.complete) {
                    $progressbar.progressbar('option', 'value', 100);
                    $this.find('.output').text(LANG.plugins.move.complete);
                    $this.find('.controls form').addClass('hide');
                } else {
                    // do it again
                    nextStep(skip);
                }
            }
        );
    };



    // attach AJAX actions to buttons
    $this.find('.ctl-continue').click(function (e) {
        e.preventDefault();

        // move in progress, no more preview
        jQuery('#plugin_move__preview').remove();

        // should the next error be skipped?
        var skip = e.target.form.skip.value;

        // step on it
        nextStep(skip);
    });

    $this.find('.move-autoskip-toggle').change(function() {
        if(this.checked && $this.find('.output').hasClass('has-error')) {
            nextStep(true);
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
