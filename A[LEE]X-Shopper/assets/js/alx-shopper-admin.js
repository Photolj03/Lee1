jQuery(document).ready(function($) {
    function initSortables() {
        $('.alx-sortable').each(function() {
            if (!$(this).data('ui-sortable')) {
                $(this).sortable({
                    items: '> .alx-sortable-item, > .alx-sortable-any',
                    update: function(event, ui) {
                        const $ul = $(this);
                        // Always move .alx-sortable-any to the top if present
                        const $any = $ul.find('.alx-sortable-any');
                        if ($any.length) {
                            $any.prependTo($ul);
                        }
                        // Remove all hidden inputs
                        $ul.find('input[type="hidden"]').remove();
                        // Re-add hidden inputs in new order
                        const index = $ul.data('index');
                        $ul.children('li').each(function() {
                            const $li = $(this);
                            if ($li.hasClass('alx-sortable-any')) {
                                const $checkbox = $li.find('input[type="checkbox"]');
                                if ($checkbox.is(':checked')) {
                                    $ul.append('<input type="hidden" name="alx_orders['+index+'][]" value="any">');
                                }
                            } else if ($li.hasClass('alx-sortable-item')) {
                                const val = $li.data('term');
                                $ul.append('<input type="hidden" name="alx_orders['+index+'][]" value="'+val+'">');
                            }
                        });
                    }
                });
            }
        });
    }

    // Initial call
    initSortables();

    // Live update for number of dropdowns
    $('input[name="alx_shopper_num_dropdowns"]').on('input change', function() {
        const num = parseInt($(this).val(), 10) || 2;
        $('.alx-dynamic-row').each(function(i) {
            if (i < num) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        setTimeout(initSortables, 100);
    }).trigger('change');

    // Live update: when attribute changes, reload values via AJAX
    $(document).on('change', '.alx-dropdown-attribute', function() {
        const $row = $(this).closest('.alx-dynamic-row');
        const attr = $(this).val();
        const index = $row.data('index');
        const data = {
            action: 'alx_get_attribute_terms',
            taxonomy: attr,
            index: index
        };
        if(attr) {
            $.post(ajaxurl, data, function(response) {
                $row.find('.alx-sortable').parent().html(response);
                initSortables();
            });
        } else {
            $row.find('.alx-sortable').parent().html('<em>No attribute selected.</em>');
        }
    });
});