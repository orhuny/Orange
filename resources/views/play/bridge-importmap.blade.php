{{-- play-host.js imports ./bridge.js. Browsers and Cloudflare cache both for hours, and only
     play-host.js has ?v= in its URL, so a deploy could pair the new host script with an old
     bridge. This maps the bridge to its versioned URL too. It must come before any module script. --}}
<script type="importmap">
    {"imports": {@json(asset('js/orange/bridge.js')): @json(asset('js/orange/bridge.js').'?v='.filemtime(public_path('js/orange/bridge.js')))}}
</script>
