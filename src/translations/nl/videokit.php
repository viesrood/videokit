<?php

declare(strict_types=1);

/**
 * Dutch translations.
 */

return [
    // Field
    'Video' => 'Video',
    'Paste a YouTube or Vimeo URL' => 'Plak een YouTube- of Vimeo-URL',
    'Browse' => 'Bladeren',
    'Enter a YouTube or Vimeo video URL.' => 'Vul een YouTube- of Vimeo-video-URL in.',
    'Videos from {provider} are not allowed here.' => "Video's van {provider} zijn hier niet toegestaan.",
    'No video provider is configured yet. You can still paste a video URL, but titles and thumbnails will stay empty.' => 'Er is nog geen videobron ingesteld. Je kunt wel een video-URL plakken, maar titels en thumbnails blijven leeg.',

    // Field settings
    'Allowed providers' => 'Toegestane bronnen',
    'Leave every option unchecked to allow all configured providers.' => 'Laat alles uitgevinkt om alle ingestelde bronnen toe te staan.',
    'Show the browse button' => 'Bladerknop tonen',
    'Lets editors search and browse the configured accounts instead of only pasting a URL.' => 'Hiermee kunnen redacteuren zoeken en bladeren in de ingestelde accounts in plaats van alleen een URL plakken.',
    'Show a preview' => 'Voorbeeld tonen',
    'Shows the thumbnail, title and duration of the selected video.' => 'Toont de thumbnail, titel en duur van de gekozen video.',

    // Picker (JavaScript)
    'Cancel' => 'Annuleren',
    'Choose a video' => 'Kies een video',
    'Clear' => 'Wissen',
    'Could not reach the video service.' => 'De videodienst is niet bereikbaar.',
    'Load more' => 'Meer laden',
    'Loading...' => 'Bezig met laden...',
    'No videos found.' => "Geen video's gevonden.",
    'Nothing to show yet.' => 'Nog niets te tonen.',
    'Refresh' => 'Verversen',
    'Remove video' => 'Video verwijderen',
    'Search' => 'Zoeken',
    'Search videos' => "Zoek video's",
    'Select' => 'Kiezen',
    'This video has no stored details yet.' => 'Van deze video zijn nog geen gegevens opgeslagen.',

    // Collections
    'Uploads' => 'Uploads',
    'Videos' => "Video's",

    // Controller responses
    'Enter at least three characters.' => 'Vul minimaal drie tekens in.',
    'That is not a YouTube or Vimeo video URL.' => 'Dat is geen YouTube- of Vimeo-video-URL.',
    'That video provider is not available.' => 'Die videobron is niet beschikbaar.',
    'The request failed. Check the Craft logs for details.' => 'Het verzoek is mislukt. Bekijk de Craft-logs voor details.',
    'The video service did not respond. Try again later.' => 'De videodienst reageerde niet. Probeer het later opnieuw.',
    'This provider does not support search.' => 'Deze bron ondersteunt geen zoekfunctie.',
    'Too many requests. Wait a moment and try again.' => 'Te veel verzoeken. Wacht even en probeer het opnieuw.',
    'Unknown collection.' => 'Onbekende collectie.',
    'VideoKit is not available.' => 'VideoKit is niet beschikbaar.',

    // Front end
    'Play video' => 'Video afspelen',
    'Play video: {title}' => 'Video afspelen: {title}',
    'Watch the video on {provider}' => 'Bekijk de video op {provider}',

    // Settings
    'Every setting below can also be set per environment in config/videokit.php. Credentials are only ever used server-side and never reach the browser.' => 'Alle instellingen hieronder kun je ook per omgeving zetten in config/videokit.php. Sleutels worden uitsluitend server-side gebruikt en komen nooit in de browser.',
    'Credentials' => 'Sleutels',
    'YouTube API key' => 'YouTube API-sleutel',
    'A YouTube Data API v3 key. Without it, YouTube URLs still work but titles and thumbnails stay empty.' => 'Een YouTube Data API v3-sleutel. Zonder sleutel werken YouTube-URLs nog steeds, maar blijven titels en thumbnails leeg.',
    'YouTube channel ID' => 'YouTube-kanaal-ID',
    'The channel (UC...) whose uploads and playlists editors can browse. Leave empty to allow search only.' => 'Het kanaal (UC...) waarvan redacteuren de uploads en afspeellijsten kunnen doorbladeren. Laat leeg om alleen zoeken toe te staan.',
    'Vimeo access token' => 'Vimeo-toegangstoken',
    'A Vimeo personal access token. Keyword search additionally requires an approved Vimeo app; without one, browsing still works.' => 'Een persoonlijk Vimeo-toegangstoken. Zoeken op trefwoord vereist daarnaast een goedgekeurde Vimeo-app; zonder die goedkeuring werkt bladeren nog wel.',
    'Vimeo user ID' => 'Vimeo-gebruikers-ID',
    'Whose videos editors can browse. Leave empty to use the account the token belongs to.' => "Wiens video's redacteuren kunnen doorbladeren. Laat leeg voor het account waar het token bij hoort.",
    'Browsing' => 'Bladeren',
    'Enabled providers' => 'Ingeschakelde bronnen',
    'Videos per page' => "Video's per pagina",
    'How many results the browse modal loads at a time.' => 'Hoeveel resultaten het bladervenster per keer laadt.',
    'Caching' => 'Caching',
    'API response cache' => 'API-antwoordcache',
    'Seconds to cache provider API responses. Keep this above zero: a YouTube search costs 100 of the 10,000 daily quota units. Set to 0 to disable caching.' => 'Aantal seconden dat API-antwoorden worden bewaard. Houd dit boven nul: een YouTube-zoekopdracht kost 100 van de 10.000 dagelijkse quota-eenheden. Zet op 0 om caching uit te schakelen.',
    'Metadata lifetime' => 'Levensduur metadata',
    'Seconds before stored titles and thumbnails are fetched again while a page renders. 0 (recommended) keeps metadata until you refresh it from the VideoKit utility, so no visitor ever waits on a provider API.' => 'Aantal seconden voordat opgeslagen titels en thumbnails tijdens het renderen opnieuw worden opgehaald. 0 (aanbevolen) bewaart de metadata tot je ze ververst via de VideoKit-utility, zodat geen enkele bezoeker op een API hoeft te wachten.',
    'Request timeout' => 'Time-out',
    'Seconds to wait for a provider API.' => 'Aantal seconden wachten op een API.',
    'Privacy and embedding' => 'Privacy en embedden',
    'Use youtube-nocookie.com' => 'youtube-nocookie.com gebruiken',
    'Embeds YouTube through its no-cookie host.' => 'Embedt YouTube via de cookievrije host.',
    'Ask Vimeo not to track' => 'Vimeo vragen niet te volgen',
    'Adds dnt=1 to the Vimeo player URL.' => 'Voegt dnt=1 toe aan de Vimeo-speler-URL.',
    'Register the facade assets' => 'Facade-assets registreren',
    'Loads the bundled click-to-load script and stylesheet when videokit_facade() is used. Turn this off if you ship your own copy.' => 'Laadt het meegeleverde klik-om-te-laden-script en de stylesheet zodra videokit_facade() wordt gebruikt. Zet dit uit als je een eigen kopie meelevert.',
    'Facade aspect ratio' => 'Beeldverhouding facade',
    'Fallback ratio for the poster when the provider does not report the video dimensions.' => 'Terugvalverhouding voor de poster als de bron de videoafmetingen niet meegeeft.',
    'Facade poster width' => 'Posterbreedte facade',
    'Thumbnail width requested for the poster image.' => 'Thumbnail-breedte die voor de posterafbeelding wordt opgevraagd.',

    // Utility
    'VideoKit stores video titles, thumbnails and durations in its own table so pages never wait on a provider API. Everything here is a cache: clearing it is always safe.' => "VideoKit bewaart videotitels, thumbnails en duur in een eigen tabel, zodat pagina's nooit op een API hoeven te wachten. Alles hier is cache: leegmaken kan altijd veilig.",
    'Provider' => 'Bron',
    'Status' => 'Status',
    'Stored videos' => "Opgeslagen video's",
    'Configured' => 'Ingesteld',
    'No credential set' => 'Geen sleutel ingesteld',
    'All providers' => 'Alle bronnen',
    'Limit the action below to one provider.' => 'Beperk de actie hieronder tot een bron.',
    'Clear cache' => 'Cache legen',
    'Refresh metadata' => 'Metadata verversen',
    '{count, plural, =1{1 video} other{# videos}} cleared.' => "{count, plural, =1{1 video} other{# video's}} gewist.",
    'Refreshed {refreshed}, failed {failed}, skipped {skipped}.' => 'Ververst: {refreshed}, mislukt: {failed}, overgeslagen: {skipped}.',
    'VideoKit API responses' => 'VideoKit API-antwoorden',
];
