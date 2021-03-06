@layout('templates.default')

<!-- Page Title -->
@section('title')
    {{ Lang::line('platform/users::general.groups.update.title') }}
@endsection

<!-- Queue Styles | e.g Theme::queue_asset('name', 'path_to_css', 'dependency')-->

<!-- Styles -->
@section('styles')
@endsection

<!-- Queue Scripts -->
{{ Theme::queue_asset('bootstrap-tab','js/bootstrap/tab.js', 'jquery') }}

<!-- Scripts -->      
@section('scripts')      
@endsection

<!-- Page Content -->
@section('content')
<section id="groups-edit">
    <!-- Tertiary Navigation & Actions -->
    <header class="navbar">
        <div class="navbar-inner">
            <div class="container">
                <!-- .btn-navbar is used as the toggle for collapsed navbar content -->
                <a class="btn btn-navbar" data-toggle="collapse" data-target="#tertiary-navigation">
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </a>

                <a class="brand" href="#">{{ Lang::line('platform/users::general.groups.update.title') }}</a>

                <!-- Everything you want hidden at 940px or less, place within here -->
                <div id="tertiary-navigation" class="nav-collapse">
                    @widget('platform/menus::menus.nav', 2, 1, 'nav nav-pills', ADMIN)
                </div>
            </div>
        </div>
    </header>

    <!-- Quaternary Desktop Navigation -->
    <nav class="quaternary-navigation tabbable visible-desktop">
        <ul class="nav nav-tabs">
            <li class="active"><a href="#general" data-toggle="tab">{{ Lang::line('platform/users::general.tabs.general') }}</a></li>
            <li><a href="#permissions" data-toggle="tab">{{ Lang::line('platform/users::general.tabs.permissions') }}</a></li>
        </ul>
    </nav>

    <div class="quaternary page">
         <!-- Quaternary Mobile Navigation -->
        <nav class="hidden-desktop">
            <ul class="nav nav-stacked nav-pills">
                <li class="active"><a href="#general" data-toggle="tab">{{ Lang::line('platform/users::general.tabs.general') }}</a></li>
                <li><a href="#permissions" data-toggle="tab">{{ Lang::line('platform/users::general.tabs.permissions') }}</a></li>
            </ul>
        </nav>

        <div class="tab-content">
            <div class="tab-pane active" id="general">
                @widget('platform/users::admin.group.form.edit', $id)
            </div>
            <div class="tab-pane" id="permissions">
                @widget('platform/users::admin.group.form.permissions', $id)
            </div>
        </div>
    </div>
</section>
@endsection
