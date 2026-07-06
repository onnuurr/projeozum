/**
 * jspdf yardımcısı — fatura PDF'lerini üreten ortak util.
 *
 * NOT: Türkçe karakter desteği için Roboto-Regular.ttf font dosyası eklenip
 * jsPDF.addFileToVFS + addFont ile register edilmelidir. Şu an default font
 * kullanılıyor (ş/ğ/ı düzgün render olmayabilir). Font dosyası eklendiğinde
 * registerTurkishFont() çağrısı uncomment edilir.
 */

import jsPDF from 'jspdf'
import autoTable from 'jspdf-autotable'

export function makeInvoicePdf({ tenant, invoice }) {
	const doc = new jsPDF({ unit: 'mm', format: 'a4' })

	// registerTurkishFont(doc) — font binary eklendiğinde aktive et.
	doc.setFont('helvetica', 'bold')
	doc.setFontSize(18)
	doc.text('FATURA', 105, 18, { align: 'center' })

	doc.setFontSize(10)
	doc.setFont('helvetica', 'normal')
	doc.text(`Fatura No: ${invoice.id}`, 14, 30)
	doc.text(`Tarih: ${invoice.created_at?.slice(0, 10) ?? '-'}`, 14, 36)
	doc.text(`Durum: ${invoice.status}`, 14, 42)
	if (invoice.due_date) {
		doc.text(`Vade: ${invoice.due_date}`, 14, 48)
	}

	doc.setFont('helvetica', 'bold')
	doc.text('Tenant', 14, 60)
	doc.setFont('helvetica', 'normal')
	doc.text(tenant.name ?? '', 14, 66)
	if (tenant.legal_name) doc.text(tenant.legal_name, 14, 72)
	if (tenant.tax_number) doc.text(`VKN: ${tenant.tax_number}  ${tenant.tax_office ?? ''}`, 14, 78)
	if (tenant.address) doc.text(`${tenant.address}  ${tenant.city ?? ''}`, 14, 84)

	autoTable(doc, {
		startY: 95,
		head: [['Açıklama', 'Tutar']],
		body: [
			[invoice.note ?? '—', `${invoice.amount.toFixed(2)} ${invoice.currency}`],
		],
		theme: 'grid',
		styles: { fontSize: 10 },
		headStyles: { fillColor: [67, 56, 202] },
	})

	const finalY = doc.lastAutoTable?.finalY ?? 110
	doc.setFont('helvetica', 'bold')
	doc.setFontSize(12)
	doc.text(`Toplam: ${invoice.amount.toFixed(2)} ${invoice.currency}`, 196, finalY + 12, { align: 'right' })

	return doc
}

export function downloadInvoicePdf({ tenant, invoice }) {
	const doc = makeInvoicePdf({ tenant, invoice })
	doc.save(`fatura-${invoice.id}.pdf`)
}
