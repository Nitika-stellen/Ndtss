<?php
class Pexlechris_Adminer extends Adminer\Adminer
{
	function credentials() {
		// server, username and password for connecting to database
        $DB_HOST = DB_HOST === 'localhost:3306' ? 'localhost' : DB_HOST;
		return array($DB_HOST, DB_USER, DB_PASSWORD);
	}

	function login($login, $password) {
		return true; // login even if password is empty string
	}

	function permanentLogin($i = false) {
		// key used for permanent login
		return md5(DB_PASSWORD);
	}

    function loginForm(){
        ob_start();
        parent::loginForm();
        $form_html = ob_get_clean();
        $form_html = str_replace(
            "<table class='layout'>",
            "<table class='layout pexle_loginForm'>",
            $form_html
        );
        echo $form_html;
    }

    function head($Jb = null){

        // Required scripts & styles
		$this->print_pexlechris_adminer_required_script();
		$this->print_pexlechris_adminer_required_style();
		if ( !defined('PEXLECHRIS_ADMINER_HAVE_ACCESS_ONLY_IN_WP_DB') || true === PEXLECHRIS_ADMINER_HAVE_ACCESS_ONLY_IN_WP_DB ){
			$this->print_only_one_db_style();
		}

        // Mandatory scripts & styles
		$this->print_dark_mode_switcher_script();
		$this->print_pexlechris_adminer_ui_customizations_style();

		/**
		 * Action to allow developers to add JS and/or CSS in Adminer <head>.
		 * See plugin's FAQs, for more.
		 *
         * @since 2.0.0 Action introduced.
		 */
		do_action('pexlechris_adminer_head');
		return true;
    }

	function navigation($missing) {
        $this->print_sticky_urls();
		parent::navigation($missing);
        $this->print_dark_mode_switcher();
	}

	public function get_wp_locale()
	{
		$wp_user_locale = get_user_locale();
		$expl = explode('_', $wp_user_locale);
		$adminer_locale = $expl[0];

		/**
		 * Filter the locale of Adminer UI.
		 *
		 * @since 3.1.0
		 *
		 * @param string $adminer_locale
		 */
		return apply_filters('pexlechris_adminer_locale', $adminer_locale);
	}

	public function print_dark_mode_switcher()
	{
		echo "<big style='position: fixed; bottom: .5em; right: .5em; cursor: pointer;'>☀</big>";
		echo Adminer\script("qsl('big').onclick = adminerDarkSwitch;");
        echo "\n";
	}

    public function print_sticky_urls(){
        $sticky_links = [
            [
                'label' => __('WP Admin', 'pexlechris-adminer'),
                'url'   => admin_url(),
            ],
            [
                'label' => __('Home', 'pexlechris-adminer'),
                'url'   => home_url(),
            ],
        ];
        $sticky_links = apply_filters('pexlechris_adminer_sticky_links', $sticky_links);

        if( !$sticky_links ){
            return;
        }
        
        $sticky_links_html = array_map(function ($sticky_link) {
            $target = !empty($sticky_link['target']) ? ' target="' . esc_attr( $sticky_link['target'] ) . '"' : '';
            return '<a href="' . esc_url( $sticky_link['url'] ) . '"' . $target .'>' . esc_html( $sticky_link['label'] ) . '</a>';
        }, $sticky_links);

        echo '<p style="position: sticky; top: 0; background: var(--dim); height: 2em; line-height: 1.8em; padding: 0 1em;">';
        echo implode(' | ', $sticky_links_html);
        echo '</p>';
    }

	/**
	 * @since 4.1.0
	 * @return void
	 */
	public function print_dark_mode_switcher_script()
	{
		?>
        <script nonce="<?php echo esc_attr( Adminer\get_nonce() )?>">
            let adminerDark;

            const saved = document.cookie.match(/adminer_dark=(\d)/);
            if (saved) {
                adminerDark = +saved[1];
                adminerDarkSet();
            }else{
                adminerDark = +window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.querySelector('html').setAttribute('data-dark-mode', adminerDark);
            }


            function adminerDarkSwitch() {
                adminerDark = !adminerDark;
                adminerDarkSet();
            }

            function adminerDarkSet() {
                qsa('link[href*="dark.css"]').forEach(link => link.media = (adminerDark ? '' : 'never'));
                qs('meta[name="color-scheme"]').content = (adminerDark ? 'dark' : 'light');
                cookie('adminer_dark=' + (adminerDark ? 1 : 0), 30);
                document.querySelector('html').setAttribute('data-dark-mode', +adminerDark);
            }
        </script>
		<?php
	}

	public function print_pexlechris_adminer_ui_customizations_style()
	{
		?>
        <style>
            html:not([data-dark-mode="1"]) a:not(.jush-custom),
            html:not([data-dark-mode="1"]) a:not(.jush-custom):visited{
                color: #0051cc;
            }


            #tables a.select {
                font-size: 0;
                padding: 12px 13px 5px 13px;
                background-size: 16px;
                background-repeat: no-repeat;
                background-position: center 0;
                background-image: url('data:image/svg+xml;utf-8,<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-article" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M3 4m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"></path><path d="M7 8h10"></path><path d="M7 12h10"></path><path d="M7 16h10"></path></svg>');
                margin-left: -8px;
            }
            html[data-dark-mode="1"] #tables a.select{
                background-image: url('data:image/svg+xml;utf-8,<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-article" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="white" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M3 4m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"></path><path d="M7 8h10"></path><path d="M7 12h10"></path><path d="M7 16h10"></path></svg>');
            }

            #table thead tr td a[href$="&modify=1"] {
                font-size: 0;
                padding: 12px 13px 5px 13px;
                margin-left: -6px;
                background-size: 16px;
                background-repeat: no-repeat;
                background-position: center 0;
                background-image: url('data:image/svg+xml;utf-8,<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-edit" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"></path><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"></path><path d="M16 5l3 3"></path></svg>');
            }
            html[data-dark-mode="1"] #table thead tr td a[href$="&modify=1"]{
                background-image: url('data:image/svg+xml;utf-8,<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-edit" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="white" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"></path><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"></path><path d="M16 5l3 3"></path></svg>');
            }

            #table tbody tr td a.edit {
                font-size: 0;
                padding: 12px 11px 5px 11px;
                background-size: 16px;
                background-repeat: no-repeat;
                background-position: center 0;
                background-image: url('data:image/svg+xml;utf-8,<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-pencil" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 20h4l10.5 -10.5a2.828 2.828 0 1 0 -4 -4l-10.5 10.5v4"/><path d="M13.5 6.5l4 4"/></svg>');
            }
            html[data-dark-mode="1"] #table tbody tr td a.edit{
                background-image: url('data:image/svg+xml;utf-8,<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-pencil" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="white" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 20h4l10.5 -10.5a2.828 2.828 0 1 0 -4 -4l-10.5 10.5v4"/><path d="M13.5 6.5l4 4"/></svg>');
            }

            #tables a.active + a{
                font-weight: bold;
            }

        </style>
		<?php
	}


	/**
     * This private method contains required scripts for auto login.
     *
	 * @since 4.1.0
     * @since 4.1.1 make permanent checkbox checked.
	 * @return void
	 */
	private function print_pexlechris_adminer_required_script()
	{
		?>
        <script nonce="<?php echo esc_attr( Adminer\get_nonce() )?>">

            // auto login
            window.addEventListener('load', function(){

                function setCookie(e,t,n){var i="";if(n){var o=new Date;o.setTime(o.getTime()+1e3*n),i="; expires="+o.toUTCString()}document.cookie=e+"="+(t||"")+i+"; path=/"}
                function getCookie(e,t=null){for(var n=e+"=",i=document.cookie.split(";"),o=0;o<i.length;o++){for(var r=i[o];" "==r.charAt(0);)r=r.substring(1,r.length);if(0==r.indexOf(n))return r.substring(n.length,r.length)}return t}

                if ( null === document.querySelector('.pexle_loginForm') ) return;

                // Do following only in login screen

                var wpLocale = '<?php echo $this->get_wp_locale(); ?>';

                var langExists = !!document.querySelector( '#lang option[value="' + wpLocale + '"]' );
                var selectElement = document.querySelector('#lang select');

                if( langExists && selectElement.value != wpLocale ){
                    selectElement.value = wpLocale;
                    var event = new Event('change', { bubbles: true });
                    selectElement.dispatchEvent(event);

                }else if( document.querySelector('.error') && getCookie('pexlechris_adminer_login_tries', 0) >= 3 ) {
                    document.querySelector('.pexle_loginForm').classList.add('pexle_hide_form');

                }else{
                    // permanent login
                    const checkbox = document.querySelector('input[name="auth[permanent]"]');
                    if (!checkbox.checked) {
                        checkbox.checked = true;
                    }

                    // auto login
                    setCookie('pexlechris_adminer_login_tries', parseInt(getCookie('pexlechris_adminer_login_tries', 0)) + 1, 15)
                    document.querySelector('.pexle_loginForm + p > input').click();
                }

            });
        </script>

		<?php
	}

	/**
     * This private method contains required css rules.
     *
	 * @since 4.1.0
	 * @return void
	 */
	private function print_pexlechris_adminer_required_style()
	{
		?>
        <style>
            #lang,
            .pexle_loginForm *,
            .pexle_loginForm + p,
            #version,
            p.logout {
                display: none;
            }
            .pexle_loginForm:not(.pexle_hide_form)::before {
                content: "<?php esc_html_e('You are connecting to the database...', 'pexlechris-adminer'); ?>";
            }

            .pexle_loginForm{
                border: unset;
            }

            #menu{
                margin-top: 0;
                top: 0
            }
            #menu > h1{
                border-top: 0;
            }
        </style>
		<?php
	}

	/**
     * This private method contains required css rules, when DB_USER has only access in one DB.
     *
	 * @since 4.1.0
	 * @return void
	 */
	private function print_only_one_db_style()
	{
		?>
        <style>
            #breadcrumb > a:nth-child(2){
                width: 17px;
                display: inline-block;
                margin-left: -14px;
                color: transparent;
                margin-right: -23px;
                pointer-events: none;
            }
            #dbs{
                display: none;
            }
            .footer > div > fieldset:last-of-type:nth-child(2):not(.jsonly) > legend{
                color: transparent;
                width: 90px;
                height: 17px;
                position: relative;
            }
            .footer > div > fieldset:nth-child(2) > legend > span{
                color: var(--fg);
                position: absolute;
                top: 0;
                left: 70px;
                padding-right: 4px;
                background: var(--bg);
            }
            .footer > div > fieldset:nth-child(2) > legend > span::before{
                content: "Selected ";
                position: absolute;
                left: -66px;
            }
            .footer input[name="copy"]{
                z-index: 2;
                position: relative;
            }
            .footer > div > fieldset:nth-child(2) > div > select[name="target"],
            .footer > div > fieldset:nth-child(2) > div > input[name="move"]{
                display: none;
            }
        </style>
		<?php
	}

}