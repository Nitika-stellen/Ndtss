(function($) {
    'use strict';

    function getFieldIdsBasedOnClass(fclass) {
        const fields = document.querySelectorAll('.' + fclass);
        let fieldIds = [];

        fields.forEach(field => {
            const fieldId = field.id;
            const matches = fieldId.match(/field_(\d+)_(\d+)/);
            if (matches && matches[1] && matches[2]) {
                fieldIds.push(`input_${matches[1]}_${matches[2]}`);
            }
        });

        return fieldIds;
    }

    function populateOptions(select, options, selectedValue = '') {
        select.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Please select';
        select.appendChild(placeholder);

        if (Array.isArray(options) && options.length) {
            options.forEach(opt => {
                const option = document.createElement('option');
                option.value = opt.name;
                option.textContent = opt.name;
                if (opt.name === selectedValue) option.selected = true;
                select.appendChild(option);
            });
            select.disabled = false;
        } else {
            const empty = document.createElement('option');
            empty.value = '';
            empty.textContent = 'No options found';
            select.appendChild(empty);
            select.disabled = true;
        }
    }

    function fetchIdByName(type, name) {
        return fetch(`${my_ajax_object.ajax_url}?action=get_${type}_id_by_name&name=${encodeURIComponent(name)}`)
            .then(res => res.json())
            .then(data => data.id || null)
            .catch(() => null);
    }

    function initDynamicDropdowns() {
        const countryIds = getFieldIdsBasedOnClass('gf-country');
        const stateIds = getFieldIdsBasedOnClass('gf-state');
        const cityIds = getFieldIdsBasedOnClass('gf-city');

        if (countryIds.length !== stateIds.length || stateIds.length !== cityIds.length) {
            console.error("Mismatch in dropdown fields");
            return;
        }

        countryIds.forEach((countryId, index) => {
            const stateId = stateIds[index];
            const cityId = cityIds[index];

            const country = document.getElementById(countryId);
            const state = document.getElementById(stateId);
            const city = document.getElementById(cityId);

            if (!country || !state || !city) return;

            window.gfDropdownSelected = window.gfDropdownSelected || {};
            const selectedCountry = window.gfDropdownSelected[countryId] || country.value;
            const selectedState = window.gfDropdownSelected[stateId] || state.value;
            const selectedCity = window.gfDropdownSelected[cityId] || city.value;

            function loadStates(countryName, callback) {
                state.innerHTML = '<option>Loading states...</option>';
                state.disabled = true;
                city.innerHTML = '';
                city.disabled = true;

                fetchIdByName('country', countryName).then(countryId => {
                    if (!countryId) {
                        populateOptions(state, []);
                        populateOptions(city, []);
                        return;
                    }

                    fetch(`${my_ajax_object.ajax_url}?action=get_states&country_id=${countryId}`)
                        .then(res => res.json())
                        .then(data => {
                            populateOptions(state, data, selectedState);
                            if (typeof callback === 'function') callback();
                        });
                });
            }

            function loadCities(stateName) {
                city.innerHTML = '<option>Loading cities...</option>';
                city.disabled = true;

                fetchIdByName('state', stateName).then(stateId => {
                    if (!stateId) {
                        populateOptions(city, []);
                        return;
                    }

                    fetch(`${my_ajax_object.ajax_url}?action=get_cities&state_id=${stateId}`)
                        .then(res => res.json())
                        .then(data => {
                            populateOptions(city, data, selectedCity);
                        });
                });
            }

            if (selectedCountry) {
                loadStates(selectedCountry, () => {
                    if (selectedState) loadCities(selectedState);
                });
            }

            country.addEventListener('change', function() {
                const name = this.options[this.selectedIndex].text;
                window.gfDropdownSelected[countryId] = name;
                window.gfDropdownSelected[stateId] = '';
                window.gfDropdownSelected[cityId] = '';
                loadStates(name);
            });

            state.addEventListener('change', function() {
                const name = this.options[this.selectedIndex].text;
                window.gfDropdownSelected[stateId] = name;
                window.gfDropdownSelected[cityId] = '';
                loadCities(name);
            });

            city.addEventListener('change', function() {
                const name = this.options[this.selectedIndex].text;
                window.gfDropdownSelected[cityId] = name;
            });
        });
    }

    $(document).ready(initDynamicDropdowns);
    $(document).on('gform_post_render', initDynamicDropdowns);
    $(document).on('gform_pre_render', initDynamicDropdowns);

})(jQuery);
