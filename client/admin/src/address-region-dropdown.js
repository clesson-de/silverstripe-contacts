/**
 * Dependent dropdown for Address Region field.
 *
 * Updates the Region dropdown options when the Country field changes.
 */
(function($) {
    'use strict';

    $.entwine('ss', function($) {

        /**
         * Watch for changes to the CountryCode dropdown.
         *
         * Selector matches various CMS contexts (ModelAdmin, GridField, etc.)
         */
        $('select[name="CountryCode"]').entwine({

            /**
             * Called when the Country dropdown value changes.
             */
            onchange: function() {
                const countryCode = this.val();

                // Find the Region field in the same form
                const form = this.closest('form');
                const regionField = form.find('select[name="Region"]');

                if (!regionField.length) {
                    console.warn('Region field not found in form');
                    return;
                }

                if (!countryCode) {
                    // No country selected — clear region dropdown
                    regionField.html('<option value=""> </option>');
                    regionField.trigger('chosen:updated').trigger('liszt:updated');
                    return;
                }

                // Show loading state
                const currentValue = regionField.val();
                regionField.html('<option value="">Loading...</option>');
                console.log('[Address] Fetching regions for country:', countryCode);

                // Get current locale from HTML lang attribute or default to 'en'
                const locale = $('html').attr('lang') || 'en';
                console.log('[Address] Using locale:', locale);

                // Fetch regions for selected country
                $.ajax({
                    url: '/contacts-api/address/regions',
                    data: {
                        country: countryCode,
                        locale: locale
                    },
                    dataType: 'json',
                    success: function(response) {
                        console.log('[Address] AJAX success, response:', response);
                        if (response.debug) {
                            console.log('[Address] Debug info:', response.debug);
                        }
                        if (response.options) {
                            const optionCount = Object.keys(response.options).length;
                            console.log('[Address] Updating dropdown with', optionCount, 'options');
                            updateRegionDropdown(regionField, response.options, currentValue);
                        } else {
                            console.error('[Address] Invalid response format:', response);
                            regionField.html('<option value="">Error loading regions</option>');
                        }
                        regionField.prop('disabled', false);
                    },
                    error: function(xhr, status, error) {
                        console.error('[Address] AJAX error:', status, error);
                        console.error('[Address] Response text:', xhr.responseText);
                        console.error('[Address] Status code:', xhr.status);
                        regionField.html('<option value="">Error loading regions</option>');
                        regionField.prop('disabled', false);
                    }
                });
            },

            /**
             * Initialize on first appearance.
             */
            onmatch: function() {
                // If country is already selected, trigger onchange to load regions
                if (this.val()) {
                    this.trigger('change');
                }
                this._super();
            }
        });

    });

    /**
     * Updates the Region dropdown with new options.
     *
     * @param {jQuery} field The region dropdown field
     * @param {Object} options Key-value pairs of region options
     * @param {string} currentValue The currently selected value to preserve
     */
    function updateRegionDropdown(field, options, currentValue) {
        let html = '<option value=""> </option>';

        for (const code in options) {
            if (options.hasOwnProperty(code)) {
                const selected = (code === currentValue) ? ' selected="selected"' : '';
                html += '<option value="' + escapeHtml(code) + '"' + selected + '>' +
                        escapeHtml(options[code]) + '</option>';
            }
        }

        field.html(html);

        // Trigger chosen/liszt update if the plugin is active
        if (field.hasClass('chosen') || field.next('.chosen-container').length > 0) {
            field.trigger('chosen:updated');
        }
        if (field.hasClass('chzn-done')) {
            field.trigger('liszt:updated');
        }
    }

    /**
     * Escapes HTML special characters.
     *
     * @param {string} text
     * @return {string}
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }


})(jQuery);

