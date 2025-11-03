class SmartPhoneFieldFree {
    constructor( options ) {
        this.options = options;
        this.init();
    }

    init() {
        this.intiSmartPhoneFieldFree();
    }

    intiSmartPhoneFieldFree() {
        if (typeof intlTelInput == 'undefined') {
            return;
        }
        const input = document.querySelector(this.options.inputId);

        const iti = window.intlTelInput(input, this.configuration());

        input.addEventListener('keypress', function(e) {

        var charCode = e.which ? e.which : e.keyCode;
            if (String.fromCharCode(charCode).match(/[^0-9+]/g)) {
                e.preventDefault();
            }
        });

        this.addCountryCodeInputHandler( input, iti );

        input.addEventListener('blur', (e) => {
            this.validateNumber(input, iti);
        }); 

        input.addEventListener('keyup', (e) => {
            this.formatValidation( input, iti );
        });
    }

    configuration() {
        let field_id = `input_${this.options.fieldId}`;

        var config = {
            initialCountry: this.options.defaultCountry,
            formatOnDisplay: false,
            formatAsYouType: false,
            fixDropdownWidth: true,
            hiddenInput: function(telInputName) {
                return {
                    phone: field_id
                };
            },
            useFullscreenPopup: false
        };

        if(this.options.countrySearch) {
            config.countrySearch = true;
        }

        if (this.options.flag == "flagcode") {
            config.nationalMode = false;
            config.autoHideDialCode = false;
        } else if (this.options.flag == "flagdial" || this.options.flag == "flagwithcode") {
            config.nationalMode = false;
            config.separateDialCode = true;
        } else {
            config.nationalMode = true;
        }

        if( this.options.exIn == 'ex_only') {
            config.onlyCountries = this.options.countries.split(',');
        }

        if( this.options.exIn == 'pre_only') {
            config.excludeCountries = this.options.countries.split(',');
        }

        if( this.options.autoIp ){
            this.detectIPAddress( config );
        }

        if( this.options.placeholder ) {
            config.autoPlaceholder = 'off';
        }

        config = gform.applyFilters( 'gform_spf_options_pre_init', config, this.options.formId, this.options.fieldId);
        
        return config;
    }

    detectIPAddress(config) {
        var api_url = "https://ipinfo.io";
        config.initialCountry = "auto";
        config.geoIpLookup = function (success, failure) {
            jQuery.get(api_url, function () {}, "jsonp").always(
                function (resp) {
                    var countryCode =
                        resp && resp.country ? resp.country : "";
                    success(countryCode);
                }
            );
        };
    }

    validateNumber( input, iti ) {
        const isValid = iti.isValidNumber();

        let errorMsg = input.parentNode.parentNode.querySelector(".error-msg"),
            validMsg = input.parentNode.parentNode.querySelector(".valid-msg");

        if( input.value ) {
            if( isValid ) {
                errorMsg.classList.add('hide');
                validMsg.classList.remove('hide');
            } else {
                validMsg.classList.add('hide');
                errorMsg.classList.remove('hide');
            }
        } else {
            validMsg.classList.add('hide');
            errorMsg.classList.add('hide');
        }
    }

    formatValidation( input, iti ) {
        const isValid = iti.isValidNumber();

        let errorMsg = input.parentNode.parentNode.querySelector(".error-msg"),
            validMsg = input.parentNode.parentNode.querySelector(".valid-msg");

         if( input.value ) {
            if( isValid ) {
                errorMsg.classList.add('hide');
                validMsg.classList.remove('hide');
            } else {
                validMsg.classList.add('hide');
                errorMsg.classList.add('hide');
            }
        } else {
            validMsg.classList.add('hide');
            errorMsg.classList.add('hide');
        }
    }

    addCountryCodeInputHandler( inputElement, iti ) {

        if( this.options.flag !== 'flagcode' ) return;

        const handleCountryChange = (event) => {
            const currentCountryData = iti.getSelectedCountryData();
            const currentCode = `+${currentCountryData.dialCode}`;

            this.updateCountryCodeHandler(event.currentTarget, currentCode);
        }

        inputElement.addEventListener('keydown', handleCountryChange);
        inputElement.addEventListener('input', handleCountryChange);
        inputElement.addEventListener('countrychange', handleCountryChange);
    }

    updateCountryCodeHandler( input, currentCode ) {
        let value = input.value;

        if( currentCode && '+undefined' === currentCode || ['','+'].includes(value) ){
            return;
        }

        if (!value.startsWith(currentCode)) {
            value = value.replace(/\+/g, '');
            input.value = currentCode + value;
        }
    }
}