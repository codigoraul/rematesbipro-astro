// ─────────────────────────────────────────────
// WordPress REST API — RematesBiPro
// ─────────────────────────────────────────────
const WP_URL = import.meta.env.WORDPRESS_API_URL || 'http://localhost:10004/wp-json/wp/v2';

// Fetch genérico con manejo de errores
async function wpFetch(endpoint) {
  try {
    const res = await fetch(`${WP_URL}${endpoint}`);
    if (!res.ok) throw new Error(`WP API error: ${res.status}`);
    return await res.json();
  } catch (e) {
    console.error('WP Fetch error:', e.message);
    return [];
  }
}

// ── VEHÍCULOS ────────────────────────────────
export async function getVehiculos() {
  return wpFetch('/vehiculos?_embed&per_page=100&status=publish');
}
export async function getVehiculo(id) {
  const [post, media] = await Promise.all([
    wpFetch(`/vehiculos/${id}?_embed&acf_format=standard`),
    wpFetch(`/media?parent=${id}&per_page=20&orderby=date&order=asc`)
  ]);
  post._attachments = Array.isArray(media) ? media.map(m => m.source_url).filter(Boolean) : [];
  if (post?.acf?.galeria && Array.isArray(post.acf.galeria)) {
    const ids = post.acf.galeria.filter(g => typeof g === 'number');
    if (ids.length > 0) {
      const mediaItems = await Promise.all(ids.map(mid => wpFetch(`/media/${mid}?_fields=source_url`)));
      post.acf.galeria = mediaItems.map(m => m?.source_url).filter(Boolean);
    }
  }
  return post;
}

// ── PROPIEDADES ──────────────────────────────
export async function getPropiedades() {
  return wpFetch('/propiedades?_embed&per_page=100&status=publish');
}
export async function getPropiedad(id) {
  const [post, media] = await Promise.all([
    wpFetch(`/propiedades/${id}?_embed&acf_format=standard`),
    wpFetch(`/media?parent=${id}&per_page=20&orderby=date&order=asc`)
  ]);
  post._attachments = Array.isArray(media) ? media.map(m => m.source_url).filter(Boolean) : [];

  // Resolve ACF gallery IDs → URLs if they come as integers
  if (post?.acf?.galeria && Array.isArray(post.acf.galeria)) {
    const ids = post.acf.galeria.filter(g => typeof g === 'number');
    if (ids.length > 0) {
      const mediaItems = await Promise.all(ids.map(mid => wpFetch(`/media/${mid}?_fields=source_url`)));
      post.acf.galeria = mediaItems.map(m => m?.source_url).filter(Boolean);
    }
  }

  return post;
}

// ── OTROS ────────────────────────────────────
export async function getOtros() {
  return wpFetch('/otros?_embed&per_page=100&status=publish');
}
export async function getOtro(id) {
  const [post, media] = await Promise.all([
    wpFetch(`/otros/${id}?_embed&acf_format=standard`),
    wpFetch(`/media?parent=${id}&per_page=20&orderby=date&order=asc`)
  ]);
  post._attachments = Array.isArray(media) ? media.map(m => m.source_url).filter(Boolean) : [];
  if (post?.acf?.galeria && Array.isArray(post.acf.galeria)) {
    const ids = post.acf.galeria.filter(g => typeof g === 'number');
    if (ids.length > 0) {
      const mediaItems = await Promise.all(ids.map(mid => wpFetch(`/media/${mid}?_fields=source_url`)));
      post.acf.galeria = mediaItems.map(m => m?.source_url).filter(Boolean);
    }
  }
  return post;
}

// ── HELPERS ──────────────────────────────────

// Decodificar entidades HTML (ej: &#8211; → –, &amp; → &)
export function decodeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&#(\d+);/g, (_, n) => String.fromCharCode(Number(n)))
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#039;/g, "'")
    .replace(/&nbsp;/g, ' ');
}

// Imagen destacada del post (_embedded)
export function getFeaturedImage(post) {
  try {
    return post._embedded?.['wp:featuredmedia']?.[0]?.source_url || null;
  } catch {
    return null;
  }
}

// Campo ACF por nombre
export function getField(post, fieldName) {
  return post?.acf?.[fieldName] ?? null;
}

// Formatear precio en pesos chilenos
export function formatPrecio(valor) {
  if (!valor) return '—';
  return new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency: 'CLP',
    maximumFractionDigits: 0,
  }).format(valor);
}

// Formatear fecha — soporta YYYY-MM-DD y YYYYMMDD
export function formatFecha(fecha) {
  if (!fecha) return '—';
  const s = String(fecha).replace(/-/g, '');
  if (s.length !== 8) return String(fecha);
  const y = s.slice(0, 4);
  const m = s.slice(4, 6);
  const d = s.slice(6, 8);
  const meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
  return `${d} ${meses[parseInt(m) - 1]} ${y}`;
}
