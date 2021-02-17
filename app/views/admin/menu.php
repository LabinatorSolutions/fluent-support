<div id='alpha_app'>
    <div class="fluent_framework-app">
        <div class="fluent_framework-main-menu-items">
        </div>
        <h1>Ok Fine</h1>
        <router-link :to="{ name: 'dashboard' }">Go to Dashboard</router-link>
        <div class="fluent_framework-body">
            <router-view></router-view>
        </div>
    </div>
</div>
