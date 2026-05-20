@extends('layouts.app')

@section('title', 'Audit Log')

@section('content')
<div style="max-width:1100px">

  <div style="font-family:'Playfair Display',serif;font-size:22px;color:var(--cream);margin-bottom:6px">
    Audit Log
  </div>
  <div style="font-size:13px;color:var(--text-dim);margin-bottom:20px">
    Riwayat perubahan data dan aktivitas ekspor. Hanya-baca.
  </div>

  {{-- Tabs --}}
  <div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:1px solid var(--navy-bd);padding-bottom:0">
    <a href="{{ route('admin.audit-log', array_merge(request()->except('tab','page','export_page'), ['tab'=>'activity'])) }}"
       style="padding:8px 18px;font-size:13px;font-weight:500;border-radius:6px 6px 0 0;text-decoration:none;
              {{ $activeTab === 'activity' ? 'background:var(--navy-card);color:var(--gold);border:1px solid var(--navy-bd);border-bottom-color:var(--navy-card)' : 'color:var(--text-dim)' }}">
      Log Aktivitas
    </a>
    <a href="{{ route('admin.audit-log', array_merge(request()->except('tab','page','export_page'), ['tab'=>'exports'])) }}"
       style="padding:8px 18px;font-size:13px;font-weight:500;border-radius:6px 6px 0 0;text-decoration:none;
              {{ $activeTab === 'exports' ? 'background:var(--navy-card);color:var(--gold);border:1px solid var(--navy-bd);border-bottom-color:var(--navy-card)' : 'color:var(--text-dim)' }}">
      Log Ekspor
    </a>
  </div>

  @if($activeTab === 'activity')
  {{-- ──────────────────── ACTIVITY LOG TAB ──────────────────── --}}

  <form method="GET" action="{{ route('admin.audit-log') }}" class="chart-card" style="margin-bottom:20px">
    <input type="hidden" name="tab" value="activity">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;align-items:flex-end">
      <div>
        <div class="form-label">Pengguna</div>
        <select name="user_id" class="form-control" style="font-size:12px">
          <option value="">Semua</option>
          @foreach($users as $u)
            <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <div class="form-label">Tipe Model</div>
        <select name="model_type" class="form-control" style="font-size:12px">
          <option value="">Semua</option>
          @foreach($modelTypes as $mt)
            <option value="{{ $mt }}" {{ request('model_type') === $mt ? 'selected' : '' }}>{{ $mt }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <div class="form-label">Aksi</div>
        <select name="action" class="form-control" style="font-size:12px">
          <option value="">Semua</option>
          <option value="create" {{ request('action') === 'create' ? 'selected' : '' }}>Create</option>
          <option value="update" {{ request('action') === 'update' ? 'selected' : '' }}>Update</option>
          <option value="delete" {{ request('action') === 'delete' ? 'selected' : '' }}>Delete</option>
        </select>
      </div>
      <div>
        <div class="form-label">Dari Tanggal</div>
        <input type="date" name="date_from" class="form-control" style="font-size:12px" value="{{ request('date_from') }}">
      </div>
      <div>
        <div class="form-label">Sampai Tanggal</div>
        <input type="date" name="date_to" class="form-control" style="font-size:12px" value="{{ request('date_to') }}">
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary" style="font-size:12px;flex:1">Terapkan</button>
        <a href="{{ route('admin.audit-log', ['tab'=>'activity']) }}" class="btn btn-ghost" style="font-size:12px">Reset</a>
      </div>
    </div>
  </form>

  <div class="table-wrap">
    <div style="padding:12px 18px;border-bottom:1px solid var(--navy-bd);display:flex;align-items:center;justify-content:space-between">
      <div style="font-size:14px;font-weight:600;color:var(--cream)">Riwayat Perubahan Data</div>
      <div style="font-size:12px;color:var(--text-dim)">{{ number_format($logs->total()) }} entri</div>
    </div>
    @if($logs->isEmpty())
      <div class="empty-state" style="padding:40px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
        <div style="margin-top:8px;color:var(--text-dim)">Tidak ada entri yang cocok</div>
      </div>
    @else
      <table>
        <thead>
          <tr>
            <th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Model</th>
            <th>Field</th><th>Nilai Lama</th><th>Nilai Baru</th><th>IP</th>
          </tr>
        </thead>
        <tbody>
          @foreach($logs as $log)
          <tr>
            <td style="font-size:11px;white-space:nowrap;color:var(--text-dim)">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
            <td style="font-size:12px">{!! $log->user->name ?? '<em style="color:var(--text-muted)">sistem</em>' !!}</td>
            <td>
              @php $c = match($log->action){ 'create'=>'var(--green)','update'=>'var(--gold)','delete'=>'var(--red)',default=>'var(--text-dim)' }; @endphp
              <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:{{ $c }}">{{ $log->action }}</span>
            </td>
            <td style="font-size:12px">
              <span style="color:var(--cream)">{{ $log->model_type }}</span>
              <span style="color:var(--text-muted);font-size:10px"> #{{ $log->model_id }}</span>
            </td>
            <td style="font-size:12px;color:var(--text-dim);font-family:monospace">{{ $log->field_changed ?? '-' }}</td>
            <td style="font-size:11px;max-width:160px;overflow:hidden;text-overflow:ellipsis;color:var(--text-dim)">
              @if($log->nilai_lama && str_starts_with(trim($log->nilai_lama), '{'))
                <span title="{{ $log->nilai_lama }}" style="cursor:help;color:var(--text-muted)">[JSON]</span>
              @else{{ $log->nilai_lama ?? '-' }}@endif
            </td>
            <td style="font-size:11px;max-width:160px;overflow:hidden;text-overflow:ellipsis;color:var(--cream)">
              @if($log->nilai_baru && str_starts_with(trim($log->nilai_baru), '{'))
                <span title="{{ $log->nilai_baru }}" style="cursor:help;color:var(--text-muted)">[JSON]</span>
              @else{{ $log->nilai_baru ?? '-' }}@endif
            </td>
            <td style="font-size:11px;color:var(--text-muted);font-family:monospace">{{ $log->ip_address ?? '-' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @include('admin.partials.pagination', ['paginator' => $logs, 'pageParam' => 'page'])
    @endif
  </div>

  @else
  {{-- ──────────────────── EXPORT LOG TAB ──────────────────── --}}

  <form method="GET" action="{{ route('admin.audit-log') }}" class="chart-card" style="margin-bottom:20px">
    <input type="hidden" name="tab" value="exports">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;align-items:flex-end">
      <div>
        <div class="form-label">Pengguna</div>
        <select name="export_user_id" class="form-control" style="font-size:12px">
          <option value="">Semua</option>
          @foreach($users as $u)
            <option value="{{ $u->id }}" {{ request('export_user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <div class="form-label">Tipe Ekspor</div>
        <select name="export_type_filter" class="form-control" style="font-size:12px">
          <option value="">Semua</option>
          @foreach($exportTypes as $et)
            <option value="{{ $et }}" {{ request('export_type_filter') === $et ? 'selected' : '' }}>{{ $et }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <div class="form-label">Dari Tanggal</div>
        <input type="date" name="export_date_from" class="form-control" style="font-size:12px" value="{{ request('export_date_from') }}">
      </div>
      <div>
        <div class="form-label">Sampai Tanggal</div>
        <input type="date" name="export_date_to" class="form-control" style="font-size:12px" value="{{ request('export_date_to') }}">
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary" style="font-size:12px;flex:1">Terapkan</button>
        <a href="{{ route('admin.audit-log', ['tab'=>'exports']) }}" class="btn btn-ghost" style="font-size:12px">Reset</a>
      </div>
    </div>
  </form>

  <div class="table-wrap">
    <div style="padding:12px 18px;border-bottom:1px solid var(--navy-bd);display:flex;align-items:center;justify-content:space-between">
      <div style="font-size:14px;font-weight:600;color:var(--cream)">Riwayat Ekspor Dokumen</div>
      <div style="font-size:12px;color:var(--text-dim)">{{ number_format($exportLogs->total()) }} entri</div>
    </div>
    @if($exportLogs->isEmpty())
      <div class="empty-state" style="padding:40px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <div style="margin-top:8px;color:var(--text-dim)">Belum ada ekspor tercatat</div>
      </div>
    @else
      <table>
        <thead>
          <tr><th>Waktu</th><th>Pengguna</th><th>Tipe Ekspor</th><th>Filter</th><th style="text-align:right">Baris</th><th>IP</th></tr>
        </thead>
        <tbody>
          @foreach($exportLogs as $log)
          <tr>
            <td style="font-size:11px;white-space:nowrap;color:var(--text-dim)">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
            <td style="font-size:12px">{{ $log->user->name ?? '—' }}</td>
            <td style="font-size:12px;font-family:monospace;color:var(--gold)">{{ $log->export_type }}</td>
            <td style="font-size:11px;color:var(--text-dim);max-width:240px">
              @if($log->filters_used)
                @foreach($log->filters_used as $k => $v)
                  <span style="background:var(--navy);border-radius:4px;padding:1px 6px;margin:1px;display:inline-block">
                    {{ $k }}: <span style="color:var(--cream)">{{ $v }}</span>
                  </span>
                @endforeach
              @else—@endif
            </td>
            <td style="text-align:right;font-size:12px;color:var(--cream)">
              {{ $log->row_count !== null ? number_format($log->row_count) : '—' }}
            </td>
            <td style="font-size:11px;color:var(--text-muted);font-family:monospace">{{ $log->ip_address ?? '-' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @include('admin.partials.pagination', ['paginator' => $exportLogs, 'pageParam' => 'export_page'])
    @endif
  </div>
  @endif

</div>
@endsection
