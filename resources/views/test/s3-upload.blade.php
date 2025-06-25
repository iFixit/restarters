@extends('layouts.app')

@section('title', 'Test S3 Upload')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3>🧪 S3 Upload Test</h3>
                    <small class="text-muted">This page is only available in non-production environments</small>
                </div>
                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                        
                        @if (session('filename'))
                            <div class="mt-3">
                                <h5>Upload Results:</h5>
                                <p><strong>Filename:</strong> {{ session('filename') }}</p>
                                
                                @if (session('urls'))
                                    <div class="row">
                                        @foreach (session('urls') as $type => $url)
                                            <div class="col-md-4 mb-3">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <small>{{ ucfirst($type) }}</small>
                                                    </div>
                                                    <div class="card-body text-center">
                                                        <img src="{{ $url }}" alt="{{ $type }}" class="img-fluid mb-2" style="max-height: 150px;">
                                                        <br>
                                                        <small class="text-muted">
                                                            <a href="{{ $url }}" target="_blank">{{ $url }}</a>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="mb-4">
                        <h5>Configuration Status</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Current Upload Disk:</strong> 
                                    <span class="badge badge-{{ config('filesystems.disks.uploads.driver') === 's3' ? 'success' : 'secondary' }}">
                                        {{ config('filesystems.disks.uploads.driver', 'local') }}
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>S3 Bucket:</strong> 
                                    <code>{{ config('filesystems.disks.s3_uploads.bucket', 'Not configured') }}</code>
                                </p>
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('test.s3.upload.post') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group">
                            <label for="test_image">Select Image File:</label>
                            <input type="file" 
                                   class="form-control-file" 
                                   id="test_image" 
                                   name="test_image" 
                                   accept="image/*" 
                                   required>
                            <small class="form-text text-muted">
                                Supported formats: JPG, PNG, GIF (max 2MB recommended)
                            </small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            🚀 Test Upload
                        </button>
                    </form>

                    <hr class="my-4">

                    <div class="row">
                        <div class="col-md-6">
                            <h6>CLI Testing</h6>
                            <p>You can also test S3 connection via command line:</p>
                            <code>php artisan uploads:test-s3</code>
                            <br>
                            <small class="text-muted">Add --verbose for detailed output</small>
                        </div>
                        <div class="col-md-6">
                            <h6>Environment Variables</h6>
                            <p>Make sure these are set in your .env:</p>
                            <ul class="small">
                                <li>UPLOADS_DISK=s3</li>
                                <li>AWS_ACCESS_KEY_ID</li>
                                <li>AWS_SECRET_ACCESS_KEY</li>
                                <li>AWS_DEFAULT_REGION</li>
                                <li>AWS_BUCKET</li>
                                <li>AWS_URL</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 