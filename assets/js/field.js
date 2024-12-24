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

            // wrap
            document.querySelectorAll(".WpDatabaseHelper_field_wrap").forEach(fieldWrap => {
                this.init_field(fieldWrap, 'field_init');
            });

            // suggest
            document.querySelectorAll('.WpDatabaseHelper_field_click_to_copy').forEach(element => {
                this.click_to_copy_init(element);
            });
        },

        init_field(fieldWrap, event){

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

        form_field_select(element, event){
            let select = element.querySelector('select');
            select = jQuery(select);

            // remove old select2, .select2('destroy') not working then i have to do that.
            select.next('.select2').remove();

            setTimeout(() => {
                select.select2();
            }, 100);
        },

        form_field_color(element){
            const fieldInput = element.querySelector('input');
            const colorControl = element.querySelector('.colorControl');
            colorControl.addEventListener('change', function () {
                fieldInput.value = colorControl.value;
                fieldInput.dispatchEvent(new Event('change'));
            });
        },

        form_field_media(element){
            var divPreview = element.querySelector('.form_field_preview');
            var imagePreview = element.querySelector('.image-preview');

            element.querySelector('.hepperMeta-media-upload').addEventListener('click', function (e) {
                e.preventDefault();
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
                    imagePreview.src = attachment.url;
                    imagePreview.srcset = "";
                    imagePreview.style.display = 'inline';
                    input.dispatchEvent(new Event('change'));
                    divPreview.classList.add('has-value');
                });
                frame.open();
            });

            element.querySelector('.hepperMeta-media-remove').addEventListener('click', function (e) {
                e.preventDefault();
                var input = element.querySelector('input');
                input.value = '';
                imagePreview.src = '';
                imagePreview.style.display = 'none';
                input.dispatchEvent(new Event('change'));
                divPreview.classList.remove('has-value');
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