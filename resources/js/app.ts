import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router';
import App from './App.vue';
import '../css/app.css';
import { syncEngine } from './services/syncEngine';
import { localNotificationService } from './services/localNotifications';
import { pushNotificationService } from './services/pushNotifications';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

app.mount('#app');

// Initialize mobile offline sync and native notifications
syncEngine.init().catch(console.warn);
localNotificationService.init().catch(console.warn);
pushNotificationService.init().catch(console.warn);
