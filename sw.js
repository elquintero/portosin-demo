const V='portosin-v5-structure',SHELL=['./','index.html','manifest.webmanifest','config.json','assets/img/logo.png','assets/img/logo-white.png','assets/img/icon-192.png','assets/img/icon-512.png','assets/img/apple-touch-icon.png','assets/img/icon-maskable-512.png'];
self.addEventListener('install',e=>{e.waitUntil(caches.open(V).then(c=>c.addAll(SHELL)));self.skipWaiting()});
self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(k=>Promise.all(k.filter(x=>x!==V).map(x=>caches.delete(x)))));self.clients.claim()});
// La API nunca se cachea: evita servir datos obsoletos o respuestas de sesión.
self.addEventListener('fetch',e=>{const u=new URL(e.request.url);if(e.request.method!=='GET'||u.origin!==location.origin||(u.pathname.endsWith('/api/index.php')||u.pathname.endsWith('/api/')))return;
 e.respondWith(fetch(e.request,{cache:'no-cache'}).then(r=>{if(r.ok){const c=r.clone();caches.open(V).then(x=>x.put(e.request,c))}return r}).catch(()=>caches.match(e.request))) });
