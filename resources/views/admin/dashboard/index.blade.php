@extends('layouts.admin')

@section('content')
@include('admin.dashboard._panel', [
    'displayUser' => $displayUser,
    'availableUsers' => $availableUsers,
])
@include('admin.dashboard._scripts')
@endsection
