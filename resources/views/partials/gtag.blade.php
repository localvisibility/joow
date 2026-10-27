@if($gtm = config('services.gtm.id'))
{{-- Consent Mode v2 : tout refusé par défaut ; le conteneur n'est chargé qu'après « Accepter » (mode basique, conforme CNIL) --}}
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',functionality_storage:'granted',security_storage:'granted'});
if(/(?:^|; )joow_consent=all(?:;|$)/.test(document.cookie)){gtag('consent','update',{ad_storage:'granted',ad_user_data:'granted',ad_personalization:'granted',analytics_storage:'granted'});(function(w,d,s,l,i){w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s);j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ $gtm }}');window.__gtmLoaded=true;}</script>
@endif
