import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Register WhatsApp Inbox Alpine component BEFORE Alpine.start()
import './whatsapp/inbox';

Alpine.start();
