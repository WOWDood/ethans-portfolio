@props(['slug', 'kind' => 'project'])

<div {{ $attributes->class(['project-art', 'art-'.$slug]) }} aria-hidden="true">
    <svg viewBox="0 0 600 360" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
        @switch($slug)
            @case('kiar-posters-and-short-film')
                <rect x="136" y="64" width="150" height="220" rx="12" class="art-panel"/>
                <rect x="155" y="84" width="112" height="112" rx="6" class="art-block"/>
                <path d="m164 174 27-32 21 18 28-41 20 29m-105 71h99m-99 17h75" class="art-line"/>
                <rect x="250" y="135" width="219" height="158" rx="14" class="art-panel"/>
                <rect x="268" y="154" width="183" height="95" rx="6" class="art-block"/>
                <path d="m349 180 27 21-27 21zM273 269h173" class="art-line"/>
                <circle cx="317" cy="269" r="5" class="art-dot"/>
                @break
            @case('big-bang-bites')
                <rect x="126" y="68" width="348" height="224" rx="18" class="art-panel"/>
                <path d="M126 105h348" class="art-line art-soft"/>
                <circle cx="149" cy="87" r="3" class="art-dot"/><circle cx="163" cy="87" r="3" class="art-dot"/><circle cx="177" cy="87" r="3" class="art-dot"/>
                <rect x="151" y="130" width="117" height="133" rx="10" class="art-block"/>
                <path d="M174 203h71m-66-10a31 31 0 0 1 62 0m-31-33v-6" class="art-line"/>
                <path d="M290 143h151m-151 22h105m-105 22h131" class="art-line art-soft"/>
                <rect x="290" y="216" width="89" height="32" rx="16" class="art-block"/>
                <path d="M320 232h30" class="art-line"/>
                @break
            @case('restaurant-pos')
                <rect x="125" y="70" width="350" height="220" rx="18" class="art-panel"/>
                <path d="M125 106h350m-123 0v184" class="art-line art-soft"/>
                <rect x="151" y="131" width="52" height="44" rx="7" class="art-block"/><rect x="213" y="131" width="52" height="44" rx="7" class="art-block"/><rect x="275" y="131" width="52" height="44" rx="7" class="art-block"/>
                <rect x="151" y="186" width="52" height="44" rx="7" class="art-block"/><rect x="213" y="186" width="52" height="44" rx="7" class="art-block"/><rect x="275" y="186" width="52" height="44" rx="7" class="art-block"/>
                <path d="M373 139h78m-78 19h64m-64 19h72m-72 24h78" class="art-line art-soft"/>
                <rect x="373" y="219" width="78" height="34" rx="8" class="art-block"/>
                <path d="m404 235 6 6 12-12" class="art-line"/>
                @break
            @case('acotech-hackathon-2025')
                <circle cx="300" cy="180" r="129" class="art-orbit"/><circle cx="300" cy="180" r="93" class="art-orbit"/>
                <path d="M185 184h-30m290 0h-30M300 67v40m0 147v40M211 91l30 30m118 118 30 30M389 91l-30 30m-118 118-30 30" class="art-line"/>
                <rect x="204" y="139" width="192" height="90" rx="25" class="art-panel"/>
                <path d="m232 187 15-28h103l19 28m-137 0h137m-120-28 7 28m87-28-7 28" class="art-line"/>
                <circle cx="247" cy="204" r="7" class="art-dot"/><circle cx="353" cy="204" r="7" class="art-dot"/>
                <circle cx="300" cy="49" r="9" class="art-dot"/><circle cx="431" cy="180" r="9" class="art-dot"/><circle cx="169" cy="180" r="9" class="art-dot"/>
                <path d="M276 126q24-30 48 0m-38-10q14-16 28 0" class="art-line"/>
                @break
            @case('wind-powered-vehicle')
                <path d="M117 122h125m-152 35h139m-97 35h82m185 100h108" class="art-line art-soft"/>
                <path d="M310 107v147m-77 14h177l-24-31H258Z" class="art-line"/>
                <circle cx="266" cy="281" r="18" class="art-panel"/><circle cx="379" cy="281" r="18" class="art-panel"/>
                <circle cx="310" cy="138" r="14" class="art-dot"/>
                <path d="M310 124c-32-37-11-81 8-72 17 8 16 47-8 72Zm13 23c47-9 75 31 57 43-16 11-48-11-57-43Zm-23 4c-16 46-65 50-67 29-2-19 34-35 67-29Z" class="art-blade"/>
                <circle cx="310" cy="138" r="105" class="art-orbit"/>
                @break
            @case('wordpress-personal-website')
                <rect x="132" y="70" width="336" height="229" rx="15" class="art-panel"/>
                <path d="M132 104h336" class="art-line art-soft"/>
                <circle cx="151" cy="87" r="3" class="art-dot"/><circle cx="164" cy="87" r="3" class="art-dot"/><circle cx="177" cy="87" r="3" class="art-dot"/>
                <rect x="155" y="129" width="111" height="142" rx="6" class="art-block"/>
                <path d="M288 143h120m-120 18h143m-143 18h93m-93 61h54" class="art-line"/>
                <path d="m174 178 19 54 18-43 17 43 19-54m-81 0h29m33 0h27" class="art-line"/>
                <rect x="287" y="210" width="84" height="28" rx="14" class="art-block"/>
                @break
            @case('arduino-automatic-curtain')
                <path d="M216 139h-64V97h-39m103 84h-89m89 41h-64v47h-39m271-130h64V97h39m-103 84h89m-89 41h64v47h39" class="art-line"/>
                <rect x="216" y="101" width="168" height="159" rx="22" class="art-panel"/>
                <rect x="246" y="130" width="108" height="100" rx="9" class="art-block"/>
                <path d="M267 151v59m22-59v59m22-59v59m22-59v59" class="art-line"/>
                <circle cx="113" cy="97" r="6" class="art-dot"/><circle cx="113" cy="269" r="6" class="art-dot"/><circle cx="487" cy="97" r="6" class="art-dot"/><circle cx="487" cy="269" r="6" class="art-dot"/>
                <path d="M267 83V64m22 19V64m22 19V64m22 19V64m-66 215v19m22-19v19m22-19v19m22-19v19" class="art-line"/>
                @break
            @default
                <circle cx="300" cy="177" r="91" class="art-orbit"/>
                <ellipse cx="300" cy="177" rx="150" ry="48" transform="rotate(-32 300 177)" class="art-line"/>
                <ellipse cx="300" cy="177" rx="150" ry="48" transform="rotate(32 300 177)" class="art-line"/>
                <circle cx="300" cy="177" r="29" class="art-panel"/><circle cx="421" cy="104" r="9" class="art-dot"/>
                <path d="m282 173 11 12 24-27" class="art-line"/>
        @endswitch
    </svg>
</div>
