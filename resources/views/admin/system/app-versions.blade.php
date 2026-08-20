@extends('layouts.admin')
@section('title', __('App Versions'))
@section('content')
<div class="page-head">
    <div>
        <p class="eyebrow">{{ __('SYSTEM') }}</p>
        <h1>{{ __('App Versions') }}</h1>
        <p>{{ __('Upload APK files and manage download links for field staff.') }}</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
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

<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">{{ __('Upload APK') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <form method="POST" action="{{ route('admin.system.app-versions.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="apk" class="form-label">{{ __('APK File') }}</label>
                        <input type="file" class="form-control" name="apk" id="apk" accept=".apk" required>
                        @error('apk') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="version_name" class="form-label">{{ __('Version Name') }}</label>
                            <input type="text" class="form-control" name="version_name" id="version_name" value="{{ old('version_name') }}" placeholder="e.g. 1.2.0" required>
                            @error('version_name') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="version_code" class="form-label">{{ __('Version Code') }}</label>
                            <input type="text" class="form-control" name="version_code" id="version_code" value="{{ old('version_code') }}" placeholder="e.g. 5" required>
                            @error('version_code') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="release_notes" class="form-label">{{ __('Release Notes') }}</label>
                        <textarea class="form-control" name="release_notes" id="release_notes" rows="3" placeholder="{{ __('What changed in this version...') }}">{{ old('release_notes') }}</textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_latest" id="is_latest" value="1" @checked(old('is_latest', true))>
                        <label class="form-check-label" for="is_latest">{{ __('Set as latest version (shown to field staff automatically)') }}</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button class="btn btn-primary">{{ __('Upload') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
