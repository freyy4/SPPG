/* UI-only routing for native links shared by dashboard and settings. */
window.sbgNavigation = () => ({
    fruitNavigationTab: 'calculate',
    navigateFragment(fragment) {
        history.pushState(null, '', '#' + fragment);
        this.syncNavigation();
        if (innerWidth < 1024) this.$nextTick(() => window.scrollTo({ top: 0 }));
    },
    syncNavigation() {
        if (!('activeView' in this)) return;
        const fragment = location.hash.slice(1);
        const fruit = {'kalkulator-buah':'calculate','katalog-buah':'master','arsip-keputusan':'archive'}[fragment];
        this.navOpen = false;
        if (fruit) { this.fruitNavigationTab = fruit; this.openFruitTab(fruit); }
        else {
            this.activeView = 'dashboard';
            this.mobilePage = fragment === 'data-po' ? 'orders' : fragment === 'master-data' ? 'admin' : 'home';
            if (fragment === 'analisa') this.$nextTick(() => this.$dispatch('open-analisa-modal'));
        }
    },
    syncFruitNavigation(tab) {
        const fragment = {calculate:'kalkulator-buah',master:'katalog-buah',archive:'arsip-keputusan'}[tab];
        if (this.activeView !== 'fruit' || !fragment) return;
        // The nested reactive tab is authoritative, including edit/choose changes.
        // Reflect it without replaying tab actions or firing a hashchange loop.
        this.fruitNavigationTab = tab;
        if (location.hash !== '#' + fragment) history.pushState(null, '', '#' + fragment);
    },
    navigationActive(fragment) {
        if (this.activeView === 'fruit') return fragment === ({calculate:'kalkulator-buah',master:'katalog-buah',archive:'arsip-keputusan'}[this.fruitNavigationTab]);
        return fragment === ({home:'ringkasan',orders:'data-po',admin:'master-data'}[this.mobilePage]);
    },
});
window.sbgAccountMenu = () => ({
    accountOpen: false,
    positionAccount() {
        const trigger = this.$refs.accountTrigger.getBoundingClientRect();
        const panel = this.$refs.accountPanel;
        const width = Math.min(panel.offsetWidth || 300, innerWidth - 16);
        const height = Math.min(panel.offsetHeight, innerHeight - 16);
        const left = Math.max(8, Math.min(trigger.left, innerWidth - width - 8));
        const preferred = trigger.bottom + 8;
        const top = preferred + height <= innerHeight - 8 ? preferred : Math.max(8, trigger.top - height - 8);
        const origin = this.$el?.getBoundingClientRect() || {left:0,top:0};
        panel.style.setProperty('--sbg-menu-left', `${left-origin.left}px`);
        panel.style.setProperty('--sbg-menu-top', `${top-origin.top}px`);
    },
    toggleAccount() {
        this.accountOpen = !this.accountOpen;
        if (this.accountOpen) this.$nextTick(() => { this.positionAccount(); this.$refs.accountProfile.focus(); });
    },
});
document.addEventListener('DOMContentLoaded', () => {
    // Fixed labels escape the scrollable rail without clipping on hover/focus.
    document.querySelectorAll('.sbg-sidebar nav a').forEach(link => {
        const position = () => link.style.setProperty('--sbg-tip-top', `${Math.max(8,Math.min(link.getBoundingClientRect().top,innerHeight-40))}px`);
        link.addEventListener('mouseenter',position); link.addEventListener('focus',position);
    });
});
