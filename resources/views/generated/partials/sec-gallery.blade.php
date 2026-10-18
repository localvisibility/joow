{{-- Section « sec-gallery » : accueil (ordre + édition par section) ou insérée dans une page via un bloc « module » ($inPage) --}}
<section class="overflow-hidden pb-4" {!! empty($inPage) ? 'style="order:'.$ord('gallery').'" data-section="gallery" data-label="Galerie"' : '' !!}>
  <div class="reveal mx-auto mb-10 max-w-6xl px-5">
    <p class="text-sm font-bold uppercase tracking-[0.3em] accent">Galerie</p>
    <h2 class="mt-3 font-display text-4xl font-bold" data-edit="content.gallery_title">{{ $t('gallery_title', 'En images') }}</h2>
  </div>
  <div class="flex w-max marq gap-5 pl-5">
    @foreach(($editMode ? $gallery : array_merge($gallery, $gallery)) as $gi => $g)
    <div class="group h-64 w-96 shrink-0 overflow-hidden rounded-3xl"><img src="{{ $g }}" class="h-full w-full object-cover transition duration-700 group-hover:scale-105" alt="{{ $b['name'] ?? '' }}" loading="lazy" @if($editMode) data-edit-img="images.gallery.{{ $gi }}" @else data-lb="{{ $g }}" @endif></div>
    @endforeach
    @if($editMode)<button type="button" class="joow-img-btn" style="position:static;height:16rem;width:12rem;border-radius:1.5rem;justify-content:center;border:2px dashed #c7d2fe;background:#f5f3ff" data-edit-img="images.gallery.{{ count($gallery) }}"><i class="fa-solid fa-plus"></i> Ajouter une photo</button>@endif
  </div>
</section>
