// jQuery(document).ready(function ($) {
//     $('body').on('focus', '.ptitle', function (e) {
//         const _ptitle = e.currentTarget;
//         const wrapper = _ptitle.closest('.inline-edit-wrapper');
//         console.log(wrapper.querySelectorAll('.WpDatabaseHelper_field ')); 
//     });
// });

document.querySelectorAll(".WpDatabaseHelper_meta_quick_edit").forEach(wrap => {
    const quick_edit_icon = wrap.querySelector('.quick_edit_icon');
    const quick_edit_field = wrap.querySelector('.quick_edit_field');
    const quick_edit_value = wrap.querySelector('.quick_edit_value');
    const meta_key = wrap.getAttribute('data-meta_key');
    const post_id = wrap.getAttribute('data-post_id');
    const form_controls = wrap.querySelectorAll('[name=' + meta_key +']');


    // toggle
    quick_edit_icon.addEventListener("click", (e) => {
        document.querySelectorAll(".WpDatabaseHelper_meta_quick_edit").forEach(otherWrap => {
            const otherField = otherWrap.querySelector('.quick_edit_field');
            const otherValueWrap = otherWrap.querySelector('.quick_edit_value');

            if (otherWrap !== wrap) {
                otherField.classList.add('hidden');
                otherValueWrap.classList.remove('hidden');
            }
        });
        quick_edit_field.classList.toggle('hidden');
        quick_edit_value.classList.toggle('hidden');
        e.stopPropagation();
    });
    document.addEventListener("click", (e) => {
        if (!wrap.contains(e.target) && !quick_edit_field.classList.contains('hidden')) {
            quick_edit_field.classList.add('hidden');
            quick_edit_value.classList.remove('hidden');
        }
    });

    // ajax
    form_controls.forEach(form_control =>{
        let timeout;
        form_control.addEventListener('change', () => {
            clearTimeout(timeout);

            let form_control_value = form_control.value;

            // checkbox
            if (form_control.type === 'checkbox') {
                form_control_value = form_control.checked ? form_control.value : '';
            }

            timeout = setTimeout(async () => {
                try {
                    const url = wpdatabasehelper_meta_js.ajax_url;
                    const formData = new FormData();
                    formData.append('action', 'wpmeta_edit__');
                    formData.append('nonce', wpdatabasehelper_meta_js.nonce);
                    formData.append('post_id', post_id);
                    formData.append('meta_key', meta_key);
                    formData.append('meta_value', form_control_value);
                    // console.log('Before Fetch:', formData.get('data'));

                    const response = await fetch(url, {
                        method: 'POST',
                        body: formData,
                    });

                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }

                    const data = await response.json(); // response.text()
                    console.log(data);
                    if (data.success) {
                        quick_edit_value.innerHTML = data.data;
                    } else {
                    }
                } catch (error) {
                    console.error('Fetch error:', error);
                }
            }, 300);
        });

    });
    
});
