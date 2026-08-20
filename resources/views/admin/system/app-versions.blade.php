@extends('layouts.admin')
@section('title', __('App Versions'))
@section('content')
<div class="page-head">
    <div>
        <p class="eyebrow">{{ __('SYSTEM') }}</p>
        <h1>{{ __('App Versions') }}</h1>
        <p>{{ __('Upload APK files and manage download links for field staff.') }}</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('uploadModal').style.display='flex'">
        <span class="ph ph-upload-simple"></span> {{ __('Upload APK') }}
    </button>
</div>

@if(session('success'))
<div class="toast show" style="margin-bottom:1.5rem">{{ session('success') }}</div>
@endif

<div class="card">
    <div class="card-head"><h2>{{ __('Version History') }}</h2></div>
    <div class="card-body">
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Version') }}</th>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Size') }}</th>
                        <th>{{ __('Uploaded By') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="actions">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($versions as $v)
                    <tr>
                        <td><strong>{{ $v->version_name }}</strong></td>
                        <td>{{ $v->version_code }}</td>
                        <td>{{ $v->formattedFileSize() }}</td>
                        <td>{{ $v->uploader->name ?? '—' }}</td>
                        <td>{{ $v->created_at->format('d M Y, H:i') }}</td>
                        <td>
                            @if($v->is_latest)
                                <span class="badge badge-success">{{ __('Latest') }}</span>
                            @elseif($v->is_active)
                                <span class="badge badge-info">{{ __('Active') }}</span>
                            @else
                                <span class="badge badge-muted">{{ __('Archived') }}</span>
                            @endif
                        </td>
                        <td class="actions">
                            <a class="btn btn-sm btn-secondary" href="{{ route('admin.system.app-versions.download', $v) }}">{{ __('Download') }}</a>
                            @if(!$v->is_latest)
                                <form method="POST" action="{{ route('admin.system.app-versions.toggle-latest', $v) }}" style="display:inline">
                                    @csrf
                                    <button class="btn btn-sm btn-secondary">{{ __('Set Latest') }}</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.system.app-versions.destroy', $v) }}" style="display:inline" onsubmit="return confirm('{{ __('Delete this version?') }}')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                    @if($v->release_notes)
                    <tr class="muted-row">
                        <td colspan="7" style="padding: 0 1rem 0.75rem"><small class="muted">{{ __('Release Notes:') }} {{ $v->release_notes }}</small></td>
                    </tr>
                    @endif
                    @empty
                    <tr><td colspan="7" style="text-align:center; padding:2rem">{{ __('No APK versions uploaded yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $versions->links() }}
    </div>
</div>

<div id="uploadModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal-content" style="max-width:520px">
        <div class="modal-head">
            <h2>{{ __('Upload APK') }}</h2>
            <button class="modal-close" onclick="document.getElementById('uploadModal').style.display='none'">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.system.app-versions.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-body">
                <label>
                    {{ __('APK File') }}
                    <input type="file" name="apk" accept=".apk" required>
                    @error('apk') <small class="error">{{ $message }}</small> @enderror
                </label>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem">
                    <label>
                        {{ __('Version Name') }}
                        <input type="text" name="version_name" value="{{ old('version_name') }}" placeholder="e.g. 1.2.0" required>
                        @error('version_name') <small class="error">{{ $message }}</small> @enderror
                    </label>
                    <label>
                        {{ __('Version Code') }}
                        <input type="text" name="version_code" value="{{ old('version_code') }}" placeholder="e.g. 5" required>
                        @error('version_code') <small class="error">{{ $message }}</small> @enderror
                    </label>
                </div>
                <label>
                    {{ __('Release Notes') }}
                    <textarea name="release_notes" rows="3" placeholder="{{ __('What changed in this version...') }}">{{ old('release_notes') }}</textarea>
                </label>
                <label class="check-row">
                    <input type="checkbox" name="is_latest" value="1" @checked(old('is_latest', true))>
                    {{ __('Set as latest version (shown to field staff automatically)') }}
                </label>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('uploadModal').style.display='none'">{{ __('Cancel') }}</button>
                <button class="btn btn-primary">{{ __('Upload') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
