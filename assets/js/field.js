(function () {
    'use strict';

    const WpDatabaseHelper_Field = {
        init() {
            // this.script_debug = adminz_js.script_debug;
            window.addEventListener('resize', () => this.onWindowResize());
            document.addEventListener('DOMContentLoaded', () => this.onDOMContentLoaded());
        },

        onWindowResize() {
            // Something here
        },

        onDOMContentLoaded() {

            // adminz_click_to_copy
            document.querySelectorAll('.WpDatabaseHelper_field_click_to_copy').forEach(element => {
                this.click_to_copy_init(element);
            });

            // input slider
            document.querySelectorAll('.form_field_range').forEach(element => {
                this.form_field_range(element);
            });
        },

        form_field_range(element){
            const input = element.querySelector('input');
            const input_range_value = element.querySelector('.input_range_value');
            input.addEventListener('change', function(){
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



document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll(".form_field_media").forEach(function (formFieldMedia) {
        var preview = formFieldMedia.querySelector('.image-preview');

        formFieldMedia.querySelector('.hepperMeta-media-upload').addEventListener('click', function (e) {
            e.preventDefault();
            var button = this;
            var input = formFieldMedia.querySelector('input');

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
                var event = new Event('change');
                input.dispatchEvent(event);
                preview.src = attachment.url;
                preview.style.display = 'block';
            });

            frame.open();
        });

        formFieldMedia.querySelector('.hepperMeta-media-remove').addEventListener('click', function (e) {
            e.preventDefault();
            var button = this;
            var input = formFieldMedia.querySelector('input');
            input.value = '';
            preview.src = '';
            preview.style.display = 'none';
        });
    });
});
