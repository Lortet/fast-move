jQuery(function() {
    function updateStartPageTemplate($form) {
        var $checkbox = $form.find('input[name="create_start_pages"]');
        var $template = $form.find('textarea[name="start_page_template"]');
        if (!$checkbox.length || !$template.length) return;

        $template.closest('label').toggle($checkbox.is(':checked'));
    }

    jQuery('form').each(function() {
        updateStartPageTemplate(jQuery(this));
    });

    jQuery(document).on('change', 'input[name="create_start_pages"]', function() {
        updateStartPageTemplate(jQuery(this).closest('form'));
    });
});

jQuery('form.plugin_move_form').each(function(){
    var $form = jQuery(this);

    $form.find('.click-page').click(function() {
        $form.find('input[name=dst]').val($form.find('.click-page code').text());
        $form.find('.select').hide();
    }).click();

    $form.find('.click-ns').click(function() {
        $form.find('input[name=dst]').val($form.find('select[name=srcns]').val());
        $form.find('.select').show();
    });

    $form.find('select[name=srcns]').change(function() {
        $form.find('input[name=dst]').val(jQuery(this).val());
    });

});
