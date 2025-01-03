document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.wpdatabasehelper_wrap').forEach(wrap => {

        wrap.querySelectorAll(".check_all").forEach(checkAll => {
            checkAll.addEventListener('change', () => {
                document.querySelectorAll('[name="ids[]"]').forEach(checkbox => {
                    checkbox.checked = checkAll.checked;
                });
            });
        });

        let lastChecked = null;
        wrap.querySelectorAll('[name="ids[]').forEach(checkbox => {
            checkbox.addEventListener('click', (e) => {
                if (lastChecked && e.shiftKey) {
                    let inBetween = false;
                    wrap.querySelectorAll('[name="ids[]').forEach(cb => {
                        if (cb === checkbox || cb === lastChecked) {
                            inBetween = !inBetween;
                        }
                        if (inBetween) {
                            cb.checked = checkbox.checked;
                        }
                    });
                }
                lastChecked = checkbox;
            });
        });

        wrap.querySelectorAll(".span").forEach((span) => {
            span.addEventListener("click", function () {
                span.classList.add("hidden");
                const textarea = span.closest("td").querySelector(".textarea");
                textarea.classList.remove('hidden');
                textarea.focus();
            });
        });

        wrap.querySelectorAll(".textarea").forEach((textarea) => {
            // click event
            textarea.addEventListener('change', function () {
                const table_name = textarea.closest('table').getAttribute('data-table-name');
                const field_name = textarea.getAttribute('name');
                const field_id = textarea.closest('tr').querySelector('[name="id"]').value;
                const field_value = textarea.value;

                // Fetch 
                (async () => {
                    try {
                        const url = wpdatabasehelper_database.ajax_url;
                        const formData = new FormData();
                        formData.append('action', wpdatabasehelper_database.update_action_name);
                        formData.append('nonce', wpdatabasehelper_database.nonce);
                        formData.append('table_name', table_name);
                        formData.append('field_id', field_id);
                        formData.append('field_name', field_name);
                        formData.append('field_value', field_value);
                        // console.log('Before Fetch:', formData.get('data');

                        const response = await fetch(url, {
                            method: 'POST',
                            body: formData,
                        });

                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }

                        const data = await response.json(); // reponse.text()
                        console.log(data);
                        if (data.success) {
                            textarea.classList.add('hidden');
                            textarea.closest('td').querySelector('span').classList.remove('hidden');
                            textarea.closest('td').querySelector('span').textContent = data.data;
                        } else {
                        }
                    } catch (error) {
                        console.error('Fetch error:', error);
                    }
                })();
            });
            // blur event
            textarea.addEventListener('blur', function () {
                textarea.classList.add('hidden');
                textarea.closest('td').querySelector('span').classList.remove('hidden');
            });
        });

        wrap.querySelector('.box_add_record_button').addEventListener('click', function () {
            wrap.querySelector('.box_add_record').classList.toggle('hidden');
        });

        wrap.querySelector('.box_show_filter').addEventListener('click', function () {
            wrap.querySelector('.filters').classList.toggle('hidden');
        });
    });
});