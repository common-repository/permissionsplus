// this is blank
(function ($) {
                        // Hide all option settings initially.
                        $('.role-menus').hide();

                        // Show the selected option settings when the dropdown changes.
                        $('#selected-role').change(function () {
                                $('.role-menus').hide();
                                var selectedOption = $(this).val();
                                $('#' + selectedOption + '-role-menu').show();
                        });
                })(jQuery);
