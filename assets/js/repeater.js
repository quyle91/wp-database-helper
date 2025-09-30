(function () {
    'use strict';

    const WpDatabaseHelper_Repeater = {
        init() {
            window.addEventListener('resize', () => this.onWindowResize());
            document.addEventListener('DOMContentLoaded', () => this.onDOMContentLoaded());
        },

        onWindowResize() {
            // Something here
        },

        onDOMContentLoaded() {
            document.querySelectorAll('.WpDatabaseHelper_repeater_list_items').forEach(element => {
                this.init_repeater(element);
            });
        },

        init_repeater(element) {
            // Sử dụng event delegation cho toàn bộ container
            this.setupEventDelegation(element);
        },

        setupEventDelegation(container) {
            // Xử lý sự kiện click cho toàn bộ container
            container.addEventListener('click', (e) => {
                const target = e.target;

                // Xử lý nút Add New
                if (target.classList.contains('addnew') || target.closest('.addnew')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const addNewButton = target.classList.contains('addnew') ? target : target.closest('.addnew');
                    this.handleAddNew(addNewButton);
                    return;
                }

                // Xử lý nút Delete
                if (target.classList.contains('delete') || target.closest('.delete')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const deleteButton = target.classList.contains('delete') ? target : target.closest('.delete');
                    this.handleDelete(deleteButton);
                    return;
                }

                // Xử lý nút Move Up
                if (target.classList.contains('move_up_one') || target.closest('.move_up_one')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const moveUpButton = target.classList.contains('move_up_one') ? target : target.closest('.move_up_one');
                    this.handleMoveUp(moveUpButton);
                    return;
                }
            });
        },

        handleAddNew(addNewButton) {
            const container = addNewButton.closest('.WpDatabaseHelper_repeater_list_items');
            const lastElement = addNewButton.previousElementSibling;

            if (lastElement && lastElement.classList.contains('is_empty')) {
                lastElement.remove(); // Xóa thông báo "Empty"
            }

            if (lastElement && (lastElement.tagName === 'FIELDSET' || lastElement.classList.contains('repeater_field'))) {
                const clone = lastElement.cloneNode(true);

                // Xóa các giá trị đã nhập trong các field
                this.clearClonedFields(clone);

                addNewButton.parentNode.insertBefore(clone, addNewButton);

                // Cập nhật names
                this.updateNames(container);

                // Khởi tạo field mới
                if (typeof WpDatabaseHelper_Field !== 'undefined') {
                    WpDatabaseHelper_Field.init_field(container, 'repeater_init');
                }
            }
        },

        handleDelete(deleteButton) {
            const elementToDelete = deleteButton.closest('fieldset, .repeater_field');
            const container = deleteButton.closest('.WpDatabaseHelper_repeater_list_items');

            if (elementToDelete) {
                elementToDelete.remove();

                // Kiểm tra nếu container trống thì thêm thông báo
                const remainingItems = container.querySelectorAll('fieldset, .repeater_field');
                if (remainingItems.length === 0) {
                    const emptyCode = document.createElement('code');
                    emptyCode.className = 'is_empty';
                    emptyCode.innerHTML = '<small>Empty</small>';
                    const addNewButton = container.querySelector('.addnew');
                    if (addNewButton) {
                        container.insertBefore(emptyCode, addNewButton);
                    } else {
                        container.appendChild(emptyCode);
                    }
                }

                // Cập nhật names sau khi xóa
                this.updateNames(container);
            }
        },

        handleMoveUp(moveUpButton) {
            const element = moveUpButton.closest('fieldset, .repeater_field');
            const container = moveUpButton.closest('.WpDatabaseHelper_repeater_list_items');

            if (element) {
                const previousElement = element.previousElementSibling;

                if (previousElement && !previousElement.classList.contains('is_empty') &&
                    !previousElement.classList.contains('addnew')) {
                    element.parentNode.insertBefore(element, previousElement);
                    // Cập nhật names sau khi di chuyển
                    this.updateNames(container);
                }
            }
        },

        clearClonedFields(clone) {
            // Xóa giá trị trong các input
            clone.querySelectorAll('input[type="text"], input[type="email"], input[type="number"], textarea').forEach(input => {
                input.value = '';
            });

            // Reset các select về option đầu tiên
            clone.querySelectorAll('select').forEach(select => {
                select.selectedIndex = 0;

                // Xử lý Select2 nếu có
                if (select.classList.contains('select2-hidden-accessible')) {
                    const select2Container = select.nextElementSibling;
                    if (select2Container && select2Container.classList.contains('select2')) {
                        const rendered = select2Container.querySelector('.select2-selection__rendered');
                        if (rendered) {
                            rendered.title = select.options[0].text;
                            rendered.textContent = select.options[0].text;
                        }
                    }
                }
            });

            // Xóa checked trong radio và checkbox
            clone.querySelectorAll('input[type="radio"], input[type="checkbox"]').forEach(input => {
                input.checked = false;
            });
        },

        updateNames(container) {
            requestAnimationFrame(() => {
                const currentPrefix = container.getAttribute('prefix') || '';

                // Chỉ lấy child hợp lệ (FIELDSET hoặc .repeater_field)
                const validChildren = Array.from(container.children).filter(child =>
                    child.tagName === 'FIELDSET' || child.classList.contains('repeater_field')
                );

                validChildren.forEach((child, index) => {
                    const newPrefix = `${currentPrefix}[${index}]`;
                    this.searchAndReplace(child, newPrefix);
                });

                // Đệ quy cho repeater lồng nhau
                container.querySelectorAll('.WpDatabaseHelper_repeater_list_items').forEach(nestedContainer => {
                    this.updateNames(nestedContainer);
                });
            });
        },

        searchAndReplace(child, newPrefix) {
            let oldPrefix = child.getAttribute('prefix');

            // fix for <label> without old prefix
            if (!oldPrefix) {
                const parentPrefix = child.parentNode.getAttribute('prefix');
                const suffix = child.getAttribute('suffix');
                if (parentPrefix && suffix) {
                    oldPrefix = parentPrefix + "[" + suffix + "]";
                }
            }

            if (oldPrefix) {
                // set child prefix
                child.setAttribute('prefix', newPrefix);

                // fields
                child.querySelectorAll('.WpDatabaseHelper_field').forEach((adminzField) => {
                    let oldName = adminzField.getAttribute('name');
                    if (oldName && oldName.includes(oldPrefix)) {
                        let newName = oldName.replace(oldPrefix, newPrefix);
                        adminzField.setAttribute('name', newName);
                        adminzField.setAttribute('id', 'clone_' + Math.random().toString(36).substr(2, 9));
                    }
                });

                // Cập nhật các phần tử copy
                child.querySelectorAll('.get_copy').forEach((copyElement) => {
                    let dataText = copyElement.getAttribute('data-text');
                    if (dataText && dataText.includes(oldPrefix)) {
                        let newDataText = dataText.replace(oldPrefix, newPrefix);
                        copyElement.setAttribute('data-text', newDataText);
                    }
                });
            }
        }
    };

    WpDatabaseHelper_Repeater.init();
    window.WpDatabaseHelper_Repeater = WpDatabaseHelper_Repeater;
})();