// D:\Laragon\www\flatsome\wp-content\plugins\administrator-z\vendor\quyle91\wp-database-helper\assets\js\field.js
(function () {
    'use strict';

    const WpDatabaseHelper_Field = {
        init() {
            window.addEventListener('resize', () => this.onWindowResize());
            document.addEventListener('DOMContentLoaded', () => this.onDOMContentLoaded());
        },

        onWindowResize() {
            // Something here
        },

        onDOMContentLoaded() {
            // wrap
            document.querySelectorAll(".WpDatabaseHelper_field_wrap.single_field").forEach(fieldWrap => {
                this.init_field(fieldWrap, 'field_init');
            });

            // click_to_copy
            document.addEventListener('click', (e) => {
                const copyElement = e.target.closest('.WpDatabaseHelper_field_click_to_copy');
                if (copyElement) {
                    this.click_to_copy(copyElement);
                }
            });

            // form_field_settings
            document.addEventListener('click', (e) => {
                const btn_toggle = e.target.closest('.toggle_settings');
                if (btn_toggle) {
                    this.open_field_settings_modal(btn_toggle);
                }
                const btn_close = e.target.closest('.close_settings');
                if (btn_close) {
                    this.close_field_settings_modal(btn_close);
                }
            });
        },

        init_field(fieldWrap, event) {
            // input slider
            fieldWrap.querySelectorAll('.form_field_range').forEach(element => {
                this.form_field_range(element);
            });

            // input media - Sử dụng event delegation
            fieldWrap.querySelectorAll('.form_field_media').forEach(element => {
                this.form_field_media(element);
            });

            // input color
            fieldWrap.querySelectorAll('.form_field_color').forEach(element => {
                this.form_field_color(element);
            });

            // select
            fieldWrap.querySelectorAll('.form_field_select').forEach(element => {
                this.form_field_select(element, event);
            });
        },

        form_field_select(element, event) {
            let select = element.querySelector('select');

            if (!select.classList.contains('is_select2')) {
                return;
            }

            select = jQuery(select);
            select.next('.select2').remove();
            select.removeClass('select2-hidden-accessible');

            setTimeout(() => {
                select.select2();
            }, 100);
        },

        form_field_color(element) {
            const fieldInput = element.querySelector('input');
            const colorControl = element.querySelector('.colorControl');

            // Xóa event listeners cũ trước khi thêm mới
            const newColorControl = colorControl.cloneNode(true);
            colorControl.parentNode.replaceChild(newColorControl, colorControl);

            newColorControl.addEventListener('change', function () {
                fieldInput.value = newColorControl.value;
                fieldInput.dispatchEvent(new Event('change'));
            });
        },

        form_field_media(element) {
            // Xóa và tạo lại các phần tử để loại bỏ event listeners cũ
            const uploadBtn = element.querySelector('.hepperMeta-media-upload');
            const removeBtn = element.querySelector('.hepperMeta-media-remove');

            // Clone và replace để xóa event listeners cũ
            const newUploadBtn = uploadBtn.cloneNode(true);
            const newRemoveBtn = removeBtn.cloneNode(true);

            uploadBtn.parentNode.replaceChild(newUploadBtn, uploadBtn);
            removeBtn.parentNode.replaceChild(newRemoveBtn, removeBtn);

            newUploadBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleMediaUpload(element);
            });

            newRemoveBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleMediaRemove(element);
            });
        },

        handleMediaUpload(element) {
            var divPreview = element.querySelector('.form_field_preview');
            var input = element.querySelector('input');
            var frame = wp.media({
                title: wpdatabasehelper_field_js.text.upload,
                button: {
                    text: wpdatabasehelper_field_js.text.use_this_media
                },
                multiple: false
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                input.value = attachment.id;

                divPreview.innerHTML = '';
                const innerDiv = document.createElement('div');
                innerDiv.className = 'inner';

                if (attachment.mime && attachment.mime.startsWith('image/')) {
                    innerDiv.classList.add('has_value');
                    const img = document.createElement('img');
                    img.src = attachment.url;
                    img.className = 'image-preview';
                    innerDiv.appendChild(img);
                } else {
                    innerDiv.classList.add('has_value');
                    innerDiv.textContent = `(ID:${attachment.id})-${attachment.title}`;
                }

                divPreview.appendChild(innerDiv);
            });
            frame.open();
        },

        handleMediaRemove(element) {
            var divPreview = element.querySelector('.form_field_preview');
            var input = element.querySelector('input');

            divPreview.innerHTML = '';
            const newInnerDiv = document.createElement('div');
            newInnerDiv.className = 'inner no_value';
            newInnerDiv.textContent = '--';
            divPreview.appendChild(newInnerDiv);

            divPreview.classList.remove('has-value');
            input.value = '';
        },

        form_field_range(element) {
            const input = element.querySelector('input');
            const input_range_value = element.querySelector('.input_range_value');

            // Xóa event listener cũ và thêm mới
            const newInput = input.cloneNode(true);
            input.parentNode.replaceChild(newInput, input);

            newInput.addEventListener('change', function () {
                input_range_value.textContent = newInput.value;
            });
        },

        click_to_copy(element) {
            const text = element.getAttribute('data-text');
            if (text) {
                const textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.position = "fixed";
                textArea.style.opacity = "0";
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                    alert('Copied to clipboard: \n' + text);
                } catch (err) {
                    alert('Error to copy!');
                }
                document.body.removeChild(textArea);
            }
        },

        // Open modal
        open_field_settings_modal(btn) {
            // hide all other modals
            document.querySelectorAll('.form_field_settings .html_modal').forEach(m => {
                m.classList.add('hidden');
            });

            const wrap = btn.closest('.form_field_settings');
            if (wrap) {
                const modal = wrap.querySelector('.html_modal');
                if (modal) {
                    modal.classList.remove('hidden'); // chỉ mở
                }
            }
        },

        // Close modal
        close_field_settings_modal(btn) {
            const wrap = btn.closest('.form_field_settings');
            if (wrap) {
                const modal = wrap.querySelector('.html_modal');
                if (modal) {
                    modal.classList.add('hidden'); // chỉ đóng
                }
            }
        }
    };

    WpDatabaseHelper_Field.init();
    window.WpDatabaseHelper_Field = WpDatabaseHelper_Field;
})();