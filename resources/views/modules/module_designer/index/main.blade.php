@extends('layouts.app')

@section('page', 'index')

@section('content')
<div class="row clearfix">
    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
        <div class="card">
            <div class="body">
                <div class="row">
                    <div class="col-md-4 col-md-offset-4">
                        <div class="list-group">
                            <a href="javascript:void(0);" class="list-group-item">
                                {{ uctrans('menu.install_module', $module) }}
                            </a>
                            <a href="javascript:void(0);" class="list-group-item">
                                {{ uctrans('menu.create_module', $module) }}
                            </a>
                            <a href="javascript:void(0);" class="list-group-item">
                                {{ uctrans('menu.update_module', $module) }}
                            </a>
                            <a href="javascript:void(0);" class="list-group-item">
                                {{ uctrans('menu.settings', $module) }}
                            </a>
                            {{-- <a href="javascript:void(0);" class="list-group-item">
                                {{ uctrans('menu.workflows', $module) }}
                            </a> --}}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection