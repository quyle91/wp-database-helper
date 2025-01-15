document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll(".WpDatabaseHelper_meta_quick_edit").forEach(wrap => {
        const quick_edit_icon = wrap.querySelector('.quick_edit_icon');
        const quick_edit_field = wrap.querySelector('.quick_edit_field');
        const quick_edit_value = wrap.querySelector('.quick_edit_value');
        const meta_key = wrap.getAttribute('data-meta_key');
        const post_id = wrap.getAttribute('data-post_id');
        const args = wrap.getAttribute('data-args');
        const form_controls = wrap.querySelectorAll('.WpDatabaseHelper_field ');


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
        
        // toggle
        document.addEventListener("click", (e) => {
            if (!wrap.contains(e.target) && !quick_edit_field.classList.contains('hidden')) {
                quick_edit_field.classList.add('hidden');
                quick_edit_value.classList.remove('hidden');
            }
        });
        
        
        

        // Hàm tạo FormData
        function createFormData(post_id, meta_key, meta_value, meta_value_is_json, args) {
            const formData = new FormData();
            formData.append('action', 'wpmeta_edit__');
            formData.append('nonce', wpdatabasehelper_meta_js.nonce);
            formData.append('post_id', post_id);
            formData.append('meta_key', meta_key);
            formData.append('meta_value', meta_value);
            formData.append('meta_value_is_json', meta_value_is_json);
            formData.append('args', args);
            return formData;
        }

        // Hàm xử lý khi giá trị của form_control thay đổi
        function handleFormControlChange(formData, quick_edit_value) {
            // Gửi dữ liệu qua AJAX
            setTimeout(async () => {
                try {
                    const url = wpdatabasehelper_meta_js.ajax_url;
                    const response = await fetch(url, {
                        method: 'POST',
                        body: formData,
                    });

                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }

                    const data = await response.json();
                    console.log(data);
                    if (data.success) {
                        quick_edit_value.innerHTML = data.data;
                    }
                } catch (error) {
                    console.error('Fetch error:', error);
                }
            }, 300);
        }

        // Lặp qua các form_controls và gắn sự kiện
        form_controls.forEach(form_control => {
            let timeout;

            // Gắn sự kiện select2:select
            jQuery(form_control).on('select2:select', () => {
                clearTimeout(timeout);

                let form_control_value = form_control.value;
                let meta_value_is_json = false;

                // Tạo FormData
                const formData = createFormData(post_id, meta_key, form_control_value, meta_value_is_json, args);

                // Gọi hàm handleFormControlChange và truyền FormData
                handleFormControlChange(formData, quick_edit_value);
            });

            // Gắn sự kiện change
            form_control.addEventListener('change', () => {
                clearTimeout(timeout);

                let form_control_value = form_control.value;
                let meta_value_is_json = false;

                // Xử lý checkbox
                if (form_control.type === 'checkbox') {
                    form_control_value = form_control.checked ? form_control.value : '';

                    // Xử lý multiple checkboxes
                    const multiple_name = form_control.getAttribute('name');
                    const checkboxes = wrap.querySelectorAll(`[name="${multiple_name}"]`);
                    if (checkboxes.length > 1) {
                        meta_value_is_json = true;
                        form_control_value = Array.from(checkboxes)
                            .filter(checkbox => checkbox.checked)
                            .map(checkbox => checkbox.value);
                        form_control_value = JSON.stringify(form_control_value);
                    }
                }

                // Tạo FormData
                const formData = createFormData(post_id, meta_key, form_control_value, meta_value_is_json, args);

                // Gọi hàm handleFormControlChange và truyền FormData
                handleFormControlChange(formData, quick_edit_value);
            });
        });
    });
});