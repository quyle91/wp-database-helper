(function () {
    'use strict';

    const WpDatabaseHelper_Field = {
        init() {
            // this.script_debug = true;
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

            // suggest
            document.querySelectorAll('.WpDatabaseHelper_field_click_to_copy').forEach(element => {
                this.click_to_copy_init(element);
            });
        },

        init_field(fieldWrap, event) {

            // input slider
            fieldWrap.querySelectorAll('.form_field_range').forEach(element => {
                this.form_field_range(element);
            });

            // input media
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

            // stop if not a select2
            if (select.classList.contains('no_select2')) {
                return;
            }

            select = jQuery(select);

            // remove old select2, .select2('destroy') not working then i have to do that.
            select.next('.select2').remove();
            select.removeClass('select2-hidden-accessible');

            // after 0.1s
            setTimeout(() => {
                select.select2();
            }, 100);
        },

        form_field_color(element) {
            const fieldInput = element.querySelector('input');
            const colorControl = element.querySelector('.colorControl');
            colorControl.addEventListener('change', function () {
                fieldInput.value = colorControl.value;
                fieldInput.dispatchEvent(new Event('change'));
            });
        },

        form_field_media(element) {


            element.querySelector('.hepperMeta-media-upload').addEventListener('click', function (e) {
                e.preventDefault();

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
                    console.log(":::Set image and url ", attachment, input);

                    // 
                    divPreview.innerHTML = '';

                    // Tạo div .inner
                    const innerDiv = document.createElement('div');
                    innerDiv.className = 'inner';

                    if (attachment.mime && attachment.mime.startsWith('image/')) {
                        // Nếu là ảnh
                        innerDiv.classList.add('has_value');

                        const img = document.createElement('img');
                        img.src = attachment.url;
                        img.className = 'image-preview';

                        innerDiv.appendChild(img);
                    } else {
                        // Nếu không phải ảnh
                        innerDiv.classList.add('has_value');
                        innerDiv.textContent = `(ID:${attachment.id})-${attachment.title}`;
                    }

                    divPreview.appendChild(innerDiv);
                });
                frame.open();
            });

            element.querySelector('.hepperMeta-media-remove').addEventListener('click', function (e) {
                e.preventDefault();
                var divPreview = element.querySelector('.form_field_preview');
                var input = element.querySelector('input');

                // Cập nhật divPreview
                divPreview.innerHTML = ''; // Xóa hết nội dung
                const newInnerDiv = document.createElement('div');
                newInnerDiv.className = 'inner no_value';
                newInnerDiv.textContent = '--';
                divPreview.appendChild(newInnerDiv);

                // Xóa class "has-value" (nếu có)
                divPreview.classList.remove('has-value');

                // xoá input
                input.value = '';
            });
        },

        form_field_range(element) {
            const input = element.querySelector('input');
            const input_range_value = element.querySelector('.input_range_value');
            input.addEventListener('change', function () {
                input_range_value.textContent = input.value;
            });
        },

        click_to_copy_init(element) {
            element.onclick = function () {
                const text = element.getAttribute('data-text');
                if (text) {
                    const textArea = document.createElement("textarea");
                    textArea.value = text;
                    textArea.style.position = "fixed";  // Tránh việc textarea làm thay đổi layout trang web
                    textArea.style.opacity = "0";  // Làm cho textarea vô hình
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
            }
        },
    };

    WpDatabaseHelper_Field.init();
    window.WpDatabaseHelper_Field = WpDatabaseHelper_Field;
})();