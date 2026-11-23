<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Édition directe d'une page importée (ancien site Local Visibility), sans changer son design.
 *
 * La page source est découpée de façon déterministe en segments de texte (entre deux balises, dans le
 * <body>, hors scripts/styles…) et en images <img>. Le Studio affiche la page avec ces éléments repérés
 * (data-lvt / data-lvi) ; une modification remplace uniquement le segment ou l'attribut concerné dans le
 * fichier d'origine, puis le republie. Chaque version précédente est sauvegardée (annulation possible).
 */
class LegacyEditor
{
    private const SKIP = ['script', 'style', 'noscript', 'textarea', 'select', 'option', 'title', 'template', 'svg', 'head', 'iframe', 'object', 'code', 'pre'];

    public const KEEP_BACKUPS = 20;

    /** Segments de texte modifiables : [[offset, length, text brut], …] dans l'ordre du document. */
    public function segments(string $html): array
    {
        $out = [];
        $inBody = ! preg_match('#<body\b#i', $html); // fragment sans <body> : tout est du corps
        $skip = [];
        preg_match_all('#<!--.*?-->|<!\[CDATA\[.*?\]\]>|<[^>]*>|[^<]+#s', $html, $m, PREG_OFFSET_CAPTURE);
        foreach ($m[0] as [$tok, $off]) {
            if ($tok === '' ) {
                continue;
            }
            if ($tok[0] === '<') {
                if (str_starts_with($tok, '<!--') || str_starts_with($tok, '<!')) {
                    continue;
                }
                if (! preg_match('#^<\s*(/?)\s*([a-zA-Z][a-zA-Z0-9-]*)#', $tok, $t)) {
                    continue;
                }
                $close = $t[1] === '/';
                $name = strtolower($t[2]);
                if ($name === 'body' && ! $close) {
                    $inBody = true;
                }
                if (in_array($name, self::SKIP, true)) {
                    if ($close) {
                        $i = array_search($name, array_reverse($skip, true), true);
                        if ($i !== false) {
                            array_splice($skip, $i);
                        }
                    } elseif (! str_ends_with(rtrim($tok), '/>')) {
                        $skip[] = $name;
                    }
                }

                continue;
            }
            if (! $inBody || $skip || trim(html_entity_decode($tok, ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\xC2\xA0") === '') {
                continue;
            }
            $out[] = [$off, strlen($tok), $tok];
        }

        return $out;
    }

    /** Balises <img> du corps : [[offset, length, balise], …]. */
    public function images(string $html): array
    {
        $start = preg_match('#<body\b#i', $html, $b, PREG_OFFSET_CAPTURE) ? $b[0][1] : 0;
        preg_match_all('#<img\b[^>]*>#i', $html, $m, PREG_OFFSET_CAPTURE, $start);

        return array_map(fn ($x) => [$x[1], strlen($x[0]), $x[0]], $m[0]);
    }

    /** Texte lisible d'un segment. */
    public static function plain(string $raw): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /** Page repérée pour le Studio (aperçu uniquement : jamais publiée). */
    public function instrument(string $html, string $runtime): string
    {
        $edits = [];
        foreach ($this->segments($html) as $k => [$off, $len, $raw]) {
            preg_match('/^(\s*)(.*?)(\s*)$/s', $raw, $p);
            $edits[] = [$off, $len, $p[1].'<span data-lvt="'.$k.'">'.$p[2].'</span>'.$p[3]];
        }
        foreach ($this->images($html) as $j => [$off, $len, $tag]) {
            $edits[] = [$off, $len, preg_replace('#^<img\b#i', '<img data-lvi="'.$j.'"', $tag)];
        }
        usort($edits, fn ($a, $b) => $b[0] <=> $a[0]);
        foreach ($edits as [$off, $len, $rep]) {
            $html = substr_replace($html, $rep, $off, $len);
        }

        return preg_match('#</body>#i', $html) ? preg_replace('#</body>(?![\s\S]*</body>)#i', $runtime.'</body>', $html, 1) : $html.$runtime;
    }

    public function setText(string $html, int $k, string $text): ?string
    {
        $seg = $this->segments($html)[$k] ?? null;
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
        if (! $seg || $text === '') {
            return null;
        }
        [$off, $len, $raw] = $seg;
        preg_match('/^(\s*)(.*?)(\s*)$/s', $raw, $p);

        return substr_replace($html, $p[1].htmlspecialchars($text, ENT_NOQUOTES | ENT_HTML5, 'UTF-8').$p[3], $off, $len);
    }

    public function setImage(string $html, int $j, string $url): ?string
    {
        $img = $this->images($html)[$j] ?? null;
        if (! $img || ! preg_match('#^(https?://|/)#i', $url)) {
            return null;
        }
        [$off, $len, $tag] = $img;
        $u = htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $new = preg_replace('#\s(?:srcset|data-srcset)="[^"]*"#i', '', $tag);
        $new = preg_match('#\ssrc="[^"]*"#i', $new) ? preg_replace('#\ssrc="[^"]*"#i', ' src="'.$u.'"', $new, 1) : preg_replace('#^<img\b#i', '<img src="'.$u.'"', $new, 1);
        $new = preg_replace('#\sdata-src="[^"]*"#i', ' data-src="'.$u.'"', $new, 1);

        return substr_replace($html, $new, $off, $len);
    }

    /** Titre Google (<title>, og:title) et description (meta description, og:description). */
    public function setMeta(string $html, ?string $title, ?string $description): string
    {
        $e = fn ($v) => htmlspecialchars(trim($v), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($title !== null && trim($title) !== '') {
            $html = preg_replace('#<title>.*?</title>#is', '<title>'.$e($title).'</title>', $html, 1);
            $html = preg_replace('#(<meta\s+(?:property|name)="(?:og|twitter):title"\s+content=")[^"]*(")#i', '${1}'.$e($title).'${2}', $html);
        }
        if ($description !== null && trim($description) !== '') {
            $html = preg_replace('#(<meta\s+name="description"\s+content=")[^"]*(")#i', '${1}'.$e($description).'${2}', $html, 1);
            $html = preg_replace('#(<meta\s+(?:property|name)="(?:og|twitter):description"\s+content=")[^"]*(")#i', '${1}'.$e($description).'${2}', $html);
        }

        return $html;
    }

    public function meta(string $html): array
    {
        return [
            'title' => preg_match('#<title>(.*?)</title>#is', $html, $m) ? self::plain($m[1]) : '',
            'description' => preg_match('#<meta\s+name="description"\s+content="([^"]*)"#i', $html, $m) ? self::plain($m[1]) : '',
        ];
    }

    /** Script du Studio : textes modifiables en place, photos cliquables (pastille « Changer la photo »), liens neutralisés. */
    public static function runtime(): string
    {
        return <<<'HTML'
<style>
[data-lvt]{cursor:text;border-radius:3px;transition:background .15s,outline-color .15s;outline:1px dashed transparent;outline-offset:2px}
[data-lvt]:hover{outline-color:rgba(99,102,241,.7);background:rgba(99,102,241,.06)}
[data-lvt][contenteditable=true],[data-lvt][contenteditable=plaintext-only]{outline:2px solid #6366f1;background:rgba(99,102,241,.08)}
img[data-lvi]{cursor:pointer}img[data-lvi]:hover{outline:3px solid rgba(99,102,241,.85);outline-offset:-3px}
.lv-chip{position:absolute;z-index:2147483000;display:inline-flex;align-items:center;gap:.35rem;border-radius:9999px;background:rgba(79,70,229,.95);color:#fff;font:600 12px/1 system-ui,sans-serif;padding:.45rem .7rem;box-shadow:0 6px 18px rgba(0,0,0,.25);cursor:pointer;border:0}
[data-aos]{opacity:1!important;transform:none!important}
</style>
<script>
(function(){
  var send=function(m){try{parent.postMessage(Object.assign({joow:true},m),'*');}catch(e){}};
  var editing=null,orig='';
  function edit(el){
    if(editing===el)return;if(editing)editing.blur();
    editing=el;orig=el.textContent;
    try{el.contentEditable='plaintext-only';}catch(_){el.contentEditable='true';}
    el.focus();var r=document.createRange();r.selectNodeContents(el);var s=getSelection();s.removeAllRanges();s.addRange(r);
  }
  document.addEventListener('click',function(e){
    var t=e.target.closest&&e.target.closest('[data-lvt]');
    var a=e.target.closest&&e.target.closest('a,button');
    if(t){e.preventDefault();e.stopPropagation();edit(t);return;}
    var img=e.target.closest&&e.target.closest('img[data-lvi]');
    if(!img){img=(document.elementsFromPoint(e.clientX,e.clientY)||[]).find(function(x){return x.matches&&x.matches('img[data-lvi]');});}
    if(img){e.preventDefault();e.stopPropagation();send({type:'image',path:'lvi.'+img.dataset.lvi,current:img.currentSrc||img.src||''});return;}
    if(a){e.preventDefault();}
  },true);
  document.addEventListener('submit',function(e){e.preventDefault();},true);
  document.addEventListener('keydown',function(e){
    if(!editing)return;
    if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();editing.blur();}
    if(e.key==='Escape'){editing.textContent=orig;editing.blur();}
  },true);
  document.addEventListener('focusout',function(e){
    var el=e.target;if(!el||!el.dataset||el.dataset.lvt===undefined)return;
    el.contentEditable='false';if(editing===el)editing=null;
    var v=el.textContent.replace(/\s+/g,' ').trim();
    if(!v){el.textContent=orig;return;}
    if(v!==orig.replace(/\s+/g,' ').trim())send({type:'lv-text',k:+el.dataset.lvt,value:v});
  },true);
  // Pastilles « Changer la photo » au-dessus des images visibles
  function chips(){
    document.querySelectorAll('.lv-chip').forEach(function(c){c.remove();});
    document.querySelectorAll('img[data-lvi]').forEach(function(img){
      var r=img.getBoundingClientRect();if(r.width<90||r.height<60)return;
      var b=document.createElement('button');b.type='button';b.className='lv-chip';b.textContent='📷 Changer la photo';
      b.style.left=(r.left+window.scrollX+10)+'px';b.style.top=(r.top+window.scrollY+10)+'px';
      b.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();send({type:'image',path:'lvi.'+img.dataset.lvi,current:img.currentSrc||img.src||''});});
      document.body.appendChild(b);
    });
  }
  var t;function later(){clearTimeout(t);t=setTimeout(chips,250);}
  window.addEventListener('load',later);window.addEventListener('resize',later);setTimeout(chips,1200);setTimeout(chips,3500);
  send({type:'ready'});
})();
</script>
HTML;
    }

    // ───────────── fichier publié, sauvegardes ─────────────

    public function file(Site $site): string
    {
        return rtrim((string) config('services.sites_path', '/var/www/sites'), '/').'/'.$site->slug.'/index.html';
    }

    public function current(Site $site): string
    {
        return (string) \App\Services\SiteRenderer::legacyHtml($site);
    }

    /** Publie la nouvelle version après avoir sauvegardé l'actuelle. */
    public function save(Site $site, string $html): void
    {
        $file = $this->file($site);
        $dir = dirname($file);
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $bak = $dir.'/.lv-backups';
        @mkdir($bak, 0777, true);
        if (is_file($file)) {
            @copy($file, $bak.'/'.now()->format('Ymd-His-u').'.html');
            $old = glob($bak.'/*.html') ?: [];
            sort($old);
            foreach (array_slice($old, 0, max(0, count($old) - self::KEEP_BACKUPS)) as $f) {
                @unlink($f);
            }
        }
        file_put_contents($file, $html);
        @chmod($file, 0666);
        $site->forceFill(['html_content' => $html])->saveQuietly();
    }

    /** Rétablit la version précédente ; false s'il n'y en a pas. */
    public function undo(Site $site): bool
    {
        $file = $this->file($site);
        $all = glob(dirname($file).'/.lv-backups/*.html') ?: [];
        sort($all);
        $last = end($all);
        if (! $last) {
            return false;
        }
        $html = (string) file_get_contents($last);
        file_put_contents($file, $html);
        @chmod($file, 0666);
        @unlink($last);
        $site->forceFill(['html_content' => $html])->saveQuietly();

        return true;
    }

    public function backups(Site $site): int
    {
        return count(glob(dirname($this->file($site)).'/.lv-backups/*.html') ?: []);
    }

    // ───────────── assistant IA ─────────────

    /** Réécriture des textes à la demande (« rends la description des chambres plus chaleureuse »). */
    public function ai(Site $site, string $html, string $instruction): array
    {
        $segs = $this->segments($html);
        $list = [];
        $chars = 0;
        foreach ($segs as $k => [, , $raw]) {
            $t = self::plain($raw);
            if (mb_strlen($t) < 2) {
                continue;
            }
            $line = "$k\t".Str::limit($t, 400, '…');
            $chars += mb_strlen($line);
            if ($chars > 45000) {
                break;
            }
            $list[] = $line;
        }
        $meta = $this->meta($html);
        $prompt = "Tu modifies le site d'un établissement (« {$site->name} ») SANS changer son design : uniquement ses textes.\n"
            ."Voici les textes de la page, un par ligne : numéro<TAB>texte actuel.\n\n".implode("\n", $list)
            ."\n\nTitre Google actuel : {$meta['title']}\nDescription Google actuelle : {$meta['description']}\n\n"
            ."DEMANDE DU PROPRIÉTAIRE : $instruction\n\n"
            ."Règles : ne modifie que les textes concernés par la demande ; garde à peu près la même longueur pour ne pas casser la mise en page ; "
            ."français impeccable ; n'invente jamais de prix, d'avis, de notes, de labels ni d'informations pratiques absentes. "
            ."Si la demande ne concerne pas des textes (photos, couleurs, nouvelle section…), ne change rien et explique comment faire (cliquer sur la photo dans l'aperçu, etc.).\n"
            .'Réponds en JSON : {"reply":"1 à 2 phrases sur ce que tu as fait","changes":[{"k":12,"text":"nouveau texte"}],"title":"(optionnel) nouveau titre Google","description":"(optionnel) nouvelle description Google"}';
        try {
            $res = Gemini::generate([
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.4, 'responseMimeType' => 'application/json', 'maxOutputTokens' => 8000, 'thinkingConfig' => ['thinkingBudget' => 0]],
            ], 120, 'LegacyEditor');
            $d = json_decode((string) Gemini::text($res), true);
        } catch (\Throwable $e) {
            Log::warning('LegacyEditor IA : '.$e->getMessage());
            $d = null;
        }
        if (! is_array($d)) {
            return ['html' => $html, 'applied' => 0, 'reply' => "Je n'ai pas réussi à traiter la demande, réessayez dans un instant."];
        }
        $applied = 0;
        foreach (array_slice((array) ($d['changes'] ?? []), 0, 40) as $c) {
            $k = (int) ($c['k'] ?? -1);
            $text = (string) ($c['text'] ?? '');
            if (isset($segs[$k]) && trim($text) !== '' && self::plain($segs[$k][2]) !== trim($text)) {
                $new = $this->setText($html, $k, $text); // les autres segments gardent leur numéro (un texte n'est jamais vidé)
                if ($new !== null) {
                    $html = $new;
                    $applied++;
                }
            }
        }
        if (! empty($d['title']) || ! empty($d['description'])) {
            $html = $this->setMeta($html, $d['title'] ?? null, $d['description'] ?? null);
            $applied++;
        }
        $reply = trim((string) ($d['reply'] ?? ''));
        if (! $applied) {
            $reply = $reply !== '' && ! preg_match("/\\b(j'ai|c'est fait|modifi|chang|réécri|reformul)/iu", $reply) ? $reply
                : "Je n'ai rien modifié : précisez le texte à changer, ou cliquez directement dessus dans l'aperçu.";
        }

        return ['html' => $html, 'applied' => $applied, 'reply' => Str::limit($reply ?: 'C\'est fait.', 500, '…')];
    }
}
