                        <article class="collection-card">
                            <div class="collection-card-main">
                                <div class="collection-card-heading">
                                    <div>
                                        <span>{{ $collection->items_count }} {{ $collection->items_count === 1 ? __('saved.place') : __('saved.places') }}</span>
                                        <h3>{{ $collection->name }}</h3>
                                        @if($collection->start_date && $collection->end_date)
                                            <p class="collection-dates">
                                                📅 {{ \Carbon\Carbon::parse($collection->start_date)->format('M d') }} - {{ \Carbon\Carbon::parse($collection->end_date)->format('M d, Y') }}
                                                @if($collection->start_time) &middot; {{ \Carbon\Carbon::parse($collection->start_time)->format('g:i A') }} @endif
                                                @if($collection->end_time) &ndash; {{ \Carbon\Carbon::parse($collection->end_time)->format('g:i A') }} @endif
                                            </p>
                                        @endif
                                    </div>
                                    <div class="collection-card-actions">
                                        @if($collection->items_count >= 2)
                                            <a href="{{ route('route.index', ['source' => 'saved', 'collection' => $collection->collection_id]) }}">{{ __('saved_extra.generate') }}</a>
                                        @endif
                                        <form method="POST" action="{{ route('saved-places.collections.destroy', $collection->collection_id) }}" class="collection-delete-form" data-ajax-crud data-ajax-remove=".collection-card" onsubmit='return confirm(@js(__("saved.delete_collection_confirm")));'>
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="collection-delete-btn" aria-label="{{ __('saved.delete_collection_aria') }}">{{ __('saved.delete_collection') }}</button>
                                        </form>
                                    </div>
                                </div>

                                <div class="collection-attractions">
                                    @foreach($collection->items as $item)
                                        @if($item->attraction)
                                            <div class="collection-attraction-item">
                                                <a href="{{ route('attractions.show', ['id' => $item->attraction->attraction_id, 'source' => 'saved']) }}">
                                                    @if($item->attraction->images->isNotEmpty())
                                                        <img src="{{ asset($item->attraction->images->first()->image_path) }}" alt="">
                                                    @else
                                                        <span class="collection-image-fallback">ExploreMY</span>
                                                    @endif
                                                    <span>{{ $item->attraction->attraction_name }}</span>
                                                </a>
                                                <form method="POST" action="{{ route('saved-places.collections.places.destroy', [$collection->collection_id, $item->collection_item_id]) }}" class="collection-remove-form" data-ajax-crud data-update-collection onsubmit='return confirm(@js(__("saved.remove_collection_confirm")));'>
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="collection-remove-btn" aria-label="{{ __('saved.remove_named', ['name' => $item->attraction->attraction_name]) }}">&times;</button>
                                                </form>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>

                                @php $collectionAttractionIds = $collection->items->pluck('attraction_id')->all(); @endphp
                                @if($savedPlaces->whereNotIn('attraction_id', $collectionAttractionIds)->isNotEmpty())
                                    <details class="add-to-collection">
                                        <summary>{{ __('saved.add_places') }}</summary>
                                        <form method="POST" action="{{ route('saved-places.collections.places.store', $collection->collection_id) }}" data-ajax-crud data-update-collection>
                                            @csrf
                                            <label for="add-places-{{ $collection->collection_id }}" id="add-places-label-{{ $collection->collection_id }}">{{ __('saved_extra.select_add') }}</label>
                                            <div class="ms-select" data-fill-target="#add-places-{{ $collection->collection_id }}">
                                                <div class="ms-fields" role="combobox" aria-expanded="false" aria-controls="add-places-opt-{{ $collection->collection_id }}" aria-labelledby="add-places-label-{{ $collection->collection_id }}">
                                                    <div class="ms-chips"></div>
                                                    <input class="ms-search" type="text" placeholder="{{ __('saved_extra.search') }}" autocomplete="off" aria-label="{{ __('saved_extra.search_aria') }}">
                                                    <span class="ms-chevron" aria-hidden="true"></span>
                                                </div>

                                                <ul class="ms-listbox hidden" id="add-places-opt-{{ $collection->collection_id }}" role="listbox" aria-labelledby="add-places-label-{{ $collection->collection_id }}">
                                                    @foreach($savedPlaces->whereNotIn('attraction_id', $collectionAttractionIds) as $place)
                                                        <li class="ms-option" role="option" data-value="{{ $place->attraction_id }}" data-label="{{ $place->attraction->attraction_name }}" aria-selected="false" tabindex="-1">
                                                            {{ $place->attraction->attraction_name }}
                                                        </li>
                                                    @endforeach
                                                </ul>

                                                <select id="add-places-{{ $collection->collection_id }}" name="attraction_ids[]" multiple size="4" class="ms-native" hidden>
                                                    @foreach($savedPlaces->whereNotIn('attraction_id', $collectionAttractionIds) as $place)
                                                        <option value="{{ $place->attraction_id }}">{{ $place->attraction->attraction_name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <button type="submit">{{ __('saved.add_collection') }}</button>
                                        </form>
                                    </details>
                                @endif
                            </div>

                            @if($collection->items_count < 2)
                                <p class="collection-trip-note">
                                    {{ __('saved.add_one') }}
                                </p>
                            @endif
                        </article>
