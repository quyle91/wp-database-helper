jQuery(document).ready(function ($) {
    // quick edit
    $('body').on('focus', '.ptitle', function (e) {
        const _ptitle = e.currentTarget;
        const _tr = $(_ptitle.closest(".inline-edit-row"));
        const _tr_id = _tr.attr('id');
        const _inline_id = _tr_id.replace("edit-", "inline_");
        const _inline = $("#" + _inline_id);
        if (_inline.length) {
            const _inline0 = _inline[0];
            // console.log(_inline0);
            // console.log(_tr); 
            _tr.find('.WpDatabaseHelper_field').each(function (index, item) {
                let _field_name = $(item).attr('name');
                let _field_searchs = $(_inline0).find("." + _field_name);
                if (_field_searchs.length) {
                    _field_search = $(_field_searchs[0]);
                    let _field_search_value = _field_search.text();
                    console.log($(item), _field_search_value); 

                    if ($(item).is(':checkbox')) {
                        $(item).prop('checked', _field_search_value === $(item).val());
                    } else if ($(item).is(':radio')) {
                        $(item).prop('checked', $(item).val() === _field_search_value);
                    } else {
                        $(item).val(_field_search_value);
                    }

                    // fix for input checked

                    $(item).trigger('change');
                }
            });
        }
    });
});