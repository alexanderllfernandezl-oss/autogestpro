const fs = require('fs');
const {
  Document, Packer, Paragraph, HeadingLevel, TextRun, Table, TableRow, TableCell,
  WidthType, AlignmentType
} = require('docx');

const html = fs.readFileSync('Informe_AUTOGEST_PRO.html', 'utf8');
const plain = html.replace(/<style[\s\S]*?<\/style>/gi, '');
const tokens = [];
const pattern = /<(h1|h2|p|li|th|td)\b[^>]*>([\s\S]*?)<\/\1>/gi;
let match;
while ((match = pattern.exec(plain)) !== null) {
  const text = match[2].replace(/<[^>]+>/g, ' ').replace(/&nbsp;/gi, ' ').replace(/&amp;/gi, '&').replace(/&lt;/gi, '<').replace(/&gt;/gi, '>').replace(/\s+/g, ' ').trim();
  if (text) tokens.push([match[1].toLowerCase(), text]);
}

const children = [
  new Paragraph({
    alignment: AlignmentType.CENTER,
    spacing: { after: 300 },
    children: [new TextRun({ text: 'AUTOGEST PRO', bold: true, color: '1F4E78', size: 52 })]
  }),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    spacing: { after: 180 },
    children: [new TextRun({ text: 'Informe de análisis, diseño y sistema implementado', bold: true, size: 32 })]
  }),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    children: [new TextRun({ text: 'Taller Automotriz Almaraz - Sacaba, Cochabamba', size: 22 })]
  }),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    pageBreakBefore: false,
    spacing: { before: 2400 },
    children: [new TextRun({ text: 'Fecha: 3 de noviembre de 2026', size: 22 })]
  }),
  new Paragraph({ pageBreakBefore: true, children: [] })
];

for (const [tag, text] of tokens) {
  if (/^\s*(Calidad|Taller|AUTOGEST PRO|Informe)\s*$/i.test(text)) continue;
  if (tag === 'h1') {
    children.push(new Paragraph({
      heading: HeadingLevel.HEADING_1,
      children: [new TextRun({ text, bold: true, color: '1F4E78' })]
    }));
  } else if (tag === 'h2') {
    children.push(new Paragraph({
      heading: HeadingLevel.HEADING_2,
      children: [new TextRun({ text, bold: true, color: '2F75B5' })]
    }));
  } else if (tag === 'li') {
    children.push(new Paragraph({
      bullet: { level: 0 },
      spacing: { after: 100 },
      children: [new TextRun(text)]
    }));
  } else {
    children.push(new Paragraph({
      spacing: { after: 140, line: 276 },
      alignment: AlignmentType.JUSTIFIED,
      children: [new TextRun(text)]
    }));
  }
}

const document = new Document({
  title: 'AUTOGEST PRO - Informe de análisis y sistema implementado',
  creator: 'AUTOGEST PRO',
  description: 'Informe actualizado del sistema',
  sections: [{ properties: { page: { margin: { top: 1440, right: 1440, bottom: 1440, left: 1440 } } }, children }]
});

Packer.toBuffer(document).then(buffer => {
  fs.writeFileSync('Informe_AUTOGEST_PRO.docx', buffer);
  console.log('DOCX creado correctamente');
});
