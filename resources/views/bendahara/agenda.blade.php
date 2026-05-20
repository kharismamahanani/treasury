@extends('layouts.app')

@section('title', 'Agenda Kerja')

@section('content')
<div style="max-width:860px">

  {{-- Header --}}
  <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
    <div>
      <div style="font-family:'Playfair Display',serif;font-size:22px;color:var(--cream)">
        Agenda Kerja
      </div>
      <div style="font-size:13px;color:var(--text-dim);margin-top:3px">
        {{ now()->isoFormat('dddd, D MMMM Y') }}
      </div>
    </div>
    <a href="{{ route('bendahara.agenda') }}"
       class="btn btn-ghost" style="font-size:12px">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/>
      </svg>
      Muat Ulang
    </a>
  </div>

  {{-- Summary chips --}}
  <div style="display:flex;gap:10px;margin-bottom:28px;flex-wrap:wrap">
    @php
      $chips = [
        ['label'=>'Mendesak',      'count'=>$summary['mendesak'],       'color'=>'var(--red)'],
        ['label'=>'Perlu Tindakan','count'=>$summary['perlu_tindakan'],  'color'=>'var(--warn)'],
        ['label'=>'Rutin',         'count'=>$summary['rutin'],           'color'=>'#4a9fd4'],
      ];
    @endphp
    @foreach($chips as $chip)
    <div style="display:flex;align-items:center;gap:8px;background:var(--navy-card);border:1px solid var(--navy-bd);
                border-radius:20px;padding:6px 14px 6px 10px">
      <span style="width:8px;height:8px;border-radius:50%;background:{{ $chip['color'] }};flex-shrink:0"></span>
      <span style="font-size:12px;color:var(--text-dim)">{{ $chip['label'] }}</span>
      <span style="font-size:14px;font-weight:700;color:{{ $chip['color'] }}">{{ $chip['count'] }}</span>
    </div>
    @endforeach
    @if($summary['total'] === 0)
    <div style="display:flex;align-items:center;gap:8px;background:rgba(76,175,130,.08);border:1px solid rgba(76,175,130,.25);
                border-radius:20px;padding:6px 14px;font-size:12px;color:var(--green)">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
      Semua beres hari ini
    </div>
    @endif
  </div>

  {{-- Accordion groups --}}
  @php
    $groupMeta = [
      'mendesak'       => ['color'=>'var(--red)',   'icon'=>'M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z M12 9v4 M12 17h.01'],
      'perlu_tindakan' => ['color'=>'var(--warn)',  'icon'=>'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'],
      'rutin'          => ['color'=>'#4a9fd4',      'icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2 M9 3h6a1 1 0 011 1v1H8V4a1 1 0 011-1z M9 12h6 M9 16h4'],
    ];
  @endphp

  @foreach($agenda as $key => $group)
  @php $meta = $groupMeta[$key]; @endphp

  <div x-data="{ open: {{ $group['items']->count() > 0 ? 'true' : 'false' }} }"
       style="margin-bottom:14px;border:1px solid var(--navy-bd);border-radius:12px;overflow:hidden">

    {{-- Group header --}}
    <button @click="open = !open"
            style="width:100%;background:var(--navy-card);border:none;cursor:pointer;
                   padding:14px 18px;display:flex;align-items:center;gap:12px;text-align:left">
      <svg width="16" height="16" fill="none" stroke="{{ $meta['color'] }}" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0">
        @foreach(explode(' M', $meta['icon']) as $i => $d)
          <path d="{{ $i===0 ? $d : 'M'.$d }}" {{ str_contains($d,'h.01') ? 'fill="currentColor"' : '' }}/>
        @endforeach
      </svg>
      <div style="flex:1">
        <span style="font-size:14px;font-weight:600;color:{{ $meta['color'] }}">
          {{ $group['label'] }}
        </span>
        <span style="font-size:12px;color:var(--text-dim);margin-left:8px">
          {{ $group['description'] }}
        </span>
      </div>
      <span style="font-size:13px;font-weight:700;color:{{ $meta['color'] }};margin-right:8px">
        {{ $group['items']->count() }}
      </span>
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
           style="color:var(--text-dim);transition:transform .2s"
           :style="open ? 'transform:rotate(180deg)' : ''">
        <polyline points="6 9 12 15 18 9"/>
      </svg>
    </button>

    {{-- Items --}}
    <div x-show="open" x-transition>
      @if($group['items']->isEmpty())
        <div style="padding:24px 20px;text-align:center;font-size:13px;color:var(--text-muted)">
          <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"
               style="display:block;margin:0 auto 8px;color:var(--text-muted)">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
          Tidak ada item di grup ini
        </div>
      @else
        @foreach($group['items'] as $idx => $item)
        <div x-data="{ detail: false }"
             style="{{ $idx > 0 ? 'border-top:1px solid var(--navy-bd);' : '' }}padding:14px 18px">
          <div style="display:flex;align-items:flex-start;gap:12px">

            {{-- Type indicator dot --}}
            <div style="width:6px;height:6px;border-radius:50%;background:{{ $meta['color'] }};margin-top:6px;flex-shrink:0"></div>

            {{-- Main info --}}
            <div style="flex:1;min-width:0">
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <span style="font-size:13px;font-weight:600;color:var(--cream)">
                  {{ $item['nama'] }}
                </span>
                @if($item['bank_code'] && $item['bank_code'] !== '-')
                <span style="font-size:10px;color:var(--text-muted);background:var(--navy);
                             border-radius:4px;padding:1px 6px">
                  {{ $item['bank_code'] }}
                </span>
                @endif
              </div>

              <div style="display:flex;align-items:center;gap:12px;margin-top:4px;flex-wrap:wrap">
                {{-- Nominal --}}
                @if($item['nominal'])
                <span style="font-size:12px;color:var(--gold)">{{ $item['nominal_fmt'] }}</span>
                @endif

                {{-- Due date --}}
                @if($item['due_date_fmt'])
                <span style="font-size:11px;color:var(--text-dim)">
                  <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:2px">
                    <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                  </svg>
                  {{ $item['due_date_fmt'] }}
                </span>
                @endif

                {{-- Days remaining badge --}}
                @if($item['days_remaining'] !== null)
                  @php
                    $d = $item['days_remaining'];
                    $bc = $d <= 0 ? 'var(--red)' : ($d <= 7 ? 'var(--warn)' : '#4a9fd4');
                    $label = $d <= 0 ? 'Hari ini' : ($d === 1 ? 'Besok' : $d . ' hari lagi');
                  @endphp
                  <span style="font-size:10px;font-weight:600;color:{{ $bc }};
                               background:{{ $bc }}22;border-radius:10px;padding:1px 8px">
                    {{ $label }}
                  </span>
                @endif
              </div>

              {{-- Meta detail (collapsible) --}}
              @if(!empty($item['meta']))
              <div x-show="detail" x-transition style="margin-top:8px">
                <div style="background:var(--navy);border-radius:6px;padding:8px 12px;
                            display:flex;flex-wrap:wrap;gap:12px">
                  @foreach($item['meta'] as $mk => $mv)
                  @if($mv)
                  <div style="font-size:11px">
                    <span style="color:var(--text-muted);text-transform:capitalize">
                      {{ str_replace('_', ' ', $mk) }}:
                    </span>
                    <span style="color:var(--text-dim);margin-left:4px">{{ $mv }}</span>
                  </div>
                  @endif
                  @endforeach
                </div>
              </div>

              <button @click="detail = !detail"
                      style="background:none;border:none;cursor:pointer;font-size:11px;
                             color:var(--text-muted);padding:4px 0 0;display:inline-flex;align-items:center;gap:4px">
                <span x-text="detail ? 'Sembunyikan' : 'Lihat detail'"></span>
                <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                     :style="detail ? 'transform:rotate(180deg)' : ''" style="transition:transform .15s">
                  <polyline points="6 9 12 15 18 9"/>
                </svg>
              </button>
              @endif
            </div>

            {{-- Action button --}}
            <a href="{{ $item['action_url'] }}"
               style="flex-shrink:0;font-size:11px;font-weight:600;
                      color:{{ $meta['color'] }};background:{{ $meta['color'] }}18;
                      border:1px solid {{ $meta['color'] }}44;border-radius:6px;
                      padding:5px 12px;text-decoration:none;white-space:nowrap;
                      transition:background .15s"
               onmouseover="this.style.background='{{ $meta['color'] }}30'"
               onmouseout="this.style.background='{{ $meta['color'] }}18'">
              {{ $item['action_label'] }}
            </a>
          </div>
        </div>
        @endforeach
      @endif
    </div>
  </div>
  @endforeach

</div>
@endsection
