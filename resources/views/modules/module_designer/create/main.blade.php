@extends('layouts.app')

@section('page', 'index')

@section('uccello-extra-css')
    {{ Html::style('vendor/uccello/module-designer/css/app.css') }}
@show

@section('uccello-extra-script')
    {{ Html::script('vendor/uccello/module-designer/js/app.js') }}
@endsection

@section('content')
<div id="app-module-designer">
    <module-designer></module-designer>
</div>
@endsection