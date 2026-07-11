// Reddetme diyaloğu: serbest açıklama + "düzeltilmesi gereken alan" seçim maddeleri.
//
// Maddeler superadmin panelinden yönetilir ve controller'dan `rejectionReasons`
// (kategoriye göre gruplu) olarak gelir. Kullanıcı madde işaretler ve/veya açıklama
// yazar; seçimler chatbot'a otomatik aktarılır, aynı şeyi tekrar yazmak gerekmez.
//
// SweetAlert2 popup'ı body'ye teleport edildiği için Vue scoped stilleri geçmez;
// bu yüzden görünüm satır-içi (inline) stillerle verilir.
//
// Not: enjekte edilen `$swal` (AppLayout) yalnızca fire/dangerConfirm sunan bir
// sarmalayıcıdır; getPopup()/showValidationMessage() gibi statik API'ler tek Swal
// singleton'ı üzerinden çağrılır (aynı açık popup üzerinde çalışırlar).
import Swal from 'sweetalert2'

function escapeHtml(value) {
	return String(value ?? '')
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#39;')
}

const CHIP_STYLE =
	'display:inline-flex;align-items:center;gap:6px;padding:6px 11px;border:1px solid #e5e5ee;' +
	'border-radius:20px;font-size:13px;color:#333;cursor:pointer;background:#fafafc;user-select:none;'
const GROUP_TITLE_STYLE =
	'font-size:12px;font-weight:700;color:#555;text-align:left;margin:2px 0 7px;'
const LABEL_STYLE =
	'display:block;font-size:12px;font-weight:700;color:#555;text-align:left;margin:14px 0 6px;'
const TEXTAREA_STYLE =
	'width:100%;box-sizing:border-box;border:1px solid #e8e8f0;border-radius:8px;padding:9px 11px;' +
	'font-family:inherit;font-size:13px;color:#1a1a2e;outline:none;resize:vertical;min-height:70px;'

/**
 * @param {object} $swal  SweetAlert2 instance (inject '$swal')
 * @param {Array<{category: string, items: string[]}>} reasonGroups
 * @returns {Promise<null | { tags: string[], reason: string }>}
 */
export async function openRejectDialog($swal, reasonGroups) {
	const groups = Array.isArray(reasonGroups) ? reasonGroups : []

	const groupsHtml = groups
		.map((g) => {
			const chips = (g.items || [])
				.map((label) => {
					const safe = escapeHtml(label)
					return (
						`<label class="rej-chip" style="${CHIP_STYLE}">` +
						`<input type="checkbox" value="${safe}" style="margin:0;cursor:pointer;">` +
						`<span>${safe}</span></label>`
					)
				})
				.join('')

			if (!chips) return ''

			return (
				`<div style="margin-bottom:10px;">` +
				`<div style="${GROUP_TITLE_STYLE}">${escapeHtml(g.category)}</div>` +
				`<div style="display:flex;flex-wrap:wrap;gap:7px;">${chips}</div></div>`
			)
		})
		.join('')

	const hasGroups = groupsHtml.length > 0

	const html =
		`<div style="text-align:left;">` +
		(hasGroups
			? `<div style="${LABEL_STYLE}margin-top:0;">Düzeltilmesi gereken alanlar</div>${groupsHtml}`
			: '') +
		`<label style="${LABEL_STYLE}" for="rej-note">Açıklama${hasGroups ? ' (opsiyonel)' : ''}</label>` +
		`<textarea id="rej-note" style="${TEXTAREA_STYLE}" rows="3" ` +
		`placeholder="Işık, detay, açı, giydirme doğruluğu vb. neyin düzeltilmesi gerektiğini yazın…"></textarea>` +
		`</div>`

	const res = await $swal.fire({
		icon: 'question',
		title: 'Reddetme Gerekçesi',
		html,
		width: 540,
		focusConfirm: false,
		showCancelButton: true,
		confirmButtonText: 'Reddet',
		cancelButtonText: 'Vazgeç',
		customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-ghost' },
		preConfirm: () => {
			const popup = Swal.getPopup()
			const tags = Array.from(popup.querySelectorAll('.rej-chip input:checked')).map((i) => i.value)
			const reason = (popup.querySelector('#rej-note')?.value || '').trim()

			if (tags.length === 0 && reason.length < 10) {
				Swal.showValidationMessage('En az bir alan seçin veya en az 10 karakterlik açıklama yazın.')
				return false
			}

			return { tags, reason }
		},
	})

	if (!res.isConfirmed || !res.value) return null

	return res.value
}
