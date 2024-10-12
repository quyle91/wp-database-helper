(function () {
    'use strict';

    const WpDatabaseHelper_Repeater = {
        init() {
            // this.script_debug = adminz_js.script_debug;
            window.addEventListener('resize', () => this.onWindowResize());
            document.addEventListener('DOMContentLoaded', () => this.onDOMContentLoaded());
        },

        onWindowResize() {
            // Something here
        },

        onDOMContentLoaded() {

            document.querySelectorAll('.WpDatabaseHelper_repeater').forEach(element => {
                this.repeater(element);
            });
        },

        repeater(element) {
            this.repeater_attachDeleteEvent(element, element.querySelectorAll(".delete"));
            this.repeater_attachMoveUpEvent(element, element.querySelectorAll(".move_up_one"));
            this.repeater_attachAddNewEvent(element);
        },

        repeater_attachAddNewEvent(element) {
            const addNewButtons = element.querySelectorAll(".addnew");
            addNewButtons.forEach(addNewButton => {
                addNewButton.addEventListener("click", () => {
                    const lastElement = addNewButton.previousElementSibling;
                    if (lastElement) {
                        const clone = lastElement.cloneNode(true);
                        addNewButton.parentNode.insertBefore(clone, addNewButton);

                        // focus after clone
                        clone.querySelector('.adminz_field');
                        const input = clone.querySelector('.adminz_field');
                        input.focus();

                        if (input.tagName === 'INPUT') {
                            input.select();
                            const valueLength = input.value.length;
                            input.setSelectionRange(valueLength, valueLength);
                        }

                        // update names
                        const listItem = clone.parentNode;
                        this.repeater_updateNames(listItem);

                        // Reattach events to the new cloned element
                        this.repeater_attachDeleteEvent(element, clone.querySelectorAll(".delete"));
                        this.repeater_attachMoveUpEvent(element, clone.querySelectorAll(".move_up_one"));
                        this.repeater_attachAddNewEvent(clone); // Reattach for cloned addnew buttons
                    }
                });
            });
        },

        repeater_attachDeleteEvent(element, buttons) {
            buttons.forEach(button => {
                button.addEventListener("click", () => {
                    // fire before button removed
                    // update names
                    const listItem = button.closest(".adminz_repeater_list_items");
                    this.repeater_updateNames(listItem);

                    const _element = button.closest('fieldset, label');
                    _element.remove();
                });
            });
        },

        repeater_attachMoveUpEvent(element, buttons) {
            buttons.forEach(button => {
                button.addEventListener("click", () => {
                    const _element = button.closest('fieldset, label');
                    const previousElement = _element.previousElementSibling;

                    if (previousElement) {
                        _element.parentNode.insertBefore(_element, previousElement);
                    }

                    // update names
                    const listItem = button.closest(".adminz_repeater_list_items");
                    this.repeater_updateNames(listItem);
                });
            });
        },

        repeater_updateNames(parentNode) {
            // settimeout: fix child removed before call childrens
            setTimeout(() => {
                const children = parentNode.children;
                Array.from(children).forEach((child, index) => {
                    const currentPrefix = parentNode.getAttribute('prefix');
                    if (child.tagName === 'FIELDSET' || child.tagName === 'LABEL') {
                        const newPrefix = `${currentPrefix}[${index}]`;
                        this.repeater_searchAndReplace(child, newPrefix);
                    }
                });
            }, 100);
        },

        repeater_searchAndReplace(child, newPrefix) {
            let oldPrefix = child.getAttribute('prefix');

            // fix for <label> without old prefix
            if (!oldPrefix) {
                oldPrefix = child.parentNode.getAttribute('prefix') + "[" + child.getAttribute('suffix') + "]";
            }

            // set child prefix
            child.setAttribute('prefix', newPrefix);

            // fields
            // console.log(oldPrefix, newPrefix); 
            child.querySelectorAll('.adminz_field').forEach((adminzField) => {
                let oldName = adminzField.getAttribute('name');
                let newName = oldName.replace(oldPrefix, newPrefix);
                adminzField.setAttribute('name', newName);
                // console.log(adminzField, oldPrefix, newPrefix); 
            });

        },
    };

    WpDatabaseHelper_Repeater.init();
    window.WpDatabaseHelper_Repeater = WpDatabaseHelper_Repeater;
})();


