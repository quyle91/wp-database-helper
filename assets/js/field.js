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