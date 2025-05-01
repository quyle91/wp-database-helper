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

            document.querySelectorAll('.WpDatabaseHelper_repeater_list_items').forEach(element => {
                this.init_repeater(element);
            });
        },

        init_repeater(element) {
            this.attachAddNewEvent(element);
            this.attachDeleteEvent(element, element.querySelectorAll(".delete"));
            this.attachMoveUpEvent(element, element.querySelectorAll(".move_up_one"));
        },

        attachAddNewEvent(element) {   
            const addNewButtons = element.querySelectorAll(".addnew");
            addNewButtons.forEach(addNewButton => {
                addNewButton.addEventListener("click", () => {
                    const lastElement = addNewButton.previousElementSibling;
                    if (lastElement) {
                        const clone = lastElement.cloneNode(true);
                        addNewButton.parentNode.insertBefore(clone, addNewButton);

                        

                        // update names
                        const listItem = clone.parentNode;
                        this.updateNames(listItem);

                        // Reattach events to the new cloned element
                        this.attachDeleteEvent(element, clone.querySelectorAll(".delete"));
                        this.attachMoveUpEvent(element, clone.querySelectorAll(".move_up_one"));
                        // this.attachAddNewEvent(clone); // Reattach for cloned addnew buttons

                        // console.log('::WpDatabaseHelper_Field.init_field(element)::'); 
                        WpDatabaseHelper_Field.init_field(element, 'repeater_init');
                    }
                });
            });
        },

        attachDeleteEvent(element, buttons) {
            buttons.forEach(button => {
                button.addEventListener("click", () => {
                    // fire before button removed
                    // update names
                    const listItem = button.closest(".adminz_repeater_list_items");
                    this.updateNames(listItem);

                    const _element = button.closest('fieldset, .repeater_field');
                    _element.remove();
                });
            });
        },

        attachMoveUpEvent(element, buttons) {
            buttons.forEach(button => {
                button.addEventListener("click", () => {
                    const _element = button.closest('fieldset, .repeater_field');
                    const previousElement = _element.previousElementSibling;

                    if (previousElement) {
                        _element.parentNode.insertBefore(_element, previousElement);
                    }

                    // update names
                    const listItem = button.closest(".adminz_repeater_list_items");
                    this.updateNames(listItem);
                });
            });
        },

        updateNames(parentNode) {
            if(parentNode){
                // settimeout: fix child removed before call childrens
                setTimeout(() => {
                    const children = parentNode.children;
                    Array.from(children).forEach((child, index) => {
                        const currentPrefix = parentNode.getAttribute('prefix');
                        if (child.tagName === 'FIELDSET' || child.classList.contains('repeater_field')) {
                            const newPrefix = `${currentPrefix}[${index}]`;
                            this.searchAndReplace(child, newPrefix);
                        }
                    });
                }, 100);
            }
        },

        searchAndReplace(child, newPrefix) {
            let oldPrefix = child.getAttribute('prefix');

            // fix for <label> without old prefix
            if (!oldPrefix) {
                oldPrefix = child.parentNode.getAttribute('prefix') + "[" + child.getAttribute('suffix') + "]";
            }

            // set child prefix
            child.setAttribute('prefix', newPrefix);

            // fields
            // console.log(oldPrefix, newPrefix); 
            child.querySelectorAll('.WpDatabaseHelper_field').forEach((adminzField) => {
                let oldName = adminzField.getAttribute('name');
                let newName = oldName.replace(oldPrefix, newPrefix);
                adminzField.setAttribute('name', newName);
                adminzField.setAttribute('id', 'clone_'+Math.random());
                // console.log(adminzField, oldPrefix, newPrefix); 
            });

        },
    };

    WpDatabaseHelper_Repeater.init();
    window.WpDatabaseHelper_Repeater = WpDatabaseHelper_Repeater;
})();


