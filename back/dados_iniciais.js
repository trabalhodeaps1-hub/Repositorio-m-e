/* Cadastros iniciais para uso via Live Server (sem backend PHP).
   Não substitui registros existentes e só executa uma vez por origem. */
(function () {
  'use strict';
  const marcador = 'autoRelatorio_carga_inicial_areas_equip_v1';
  if (localStorage.getItem(marcador)) return;
  const ler = (chave) => { try { const v = JSON.parse(localStorage.getItem(chave) || '[]'); return Array.isArray(v) ? v : []; } catch (_) { return []; } };
  const areas = ler('autoRelatorio_areas');
  const equipamentos = ler('autoRelatorio_equipamentos');
  const novasAreas = [{"id": "AREA-PADRAO-1", "nome": "Redução", "descricao": "Alto-fornos e sistema de injeção de carvão", "icone": "🏭"}, {"id": "AREA-PADRAO-2", "nome": "Aciaria", "descricao": "Convertedores LD, fornos panela e lingotamento contínuo", "icone": "🔥"}, {"id": "AREA-PADRAO-3", "nome": "Laminação", "descricao": "Laminadores a quente e a frio, linha de acabamento", "icone": "⚙️"}];
  const novosEquipamentos = [{"id": "EQUIP-PADRAO-AF-001", "patrimonio": "AF-001", "nome": "Alto-forno", "area": "Redução", "localizacao": "Setor A - Bloco 1", "status": "Operando"}, {"id": "EQUIP-PADRAO-AF-002", "patrimonio": "AF-002", "nome": "Alto-forno", "area": "Redução", "localizacao": "Setor A - Bloco 3", "status": "Manutenção"}, {"id": "EQUIP-PADRAO-LTQ-001", "patrimonio": "LTQ-001", "nome": "Laminador de Tiras a Quente", "area": "Laminação", "localizacao": "Setor C - Linha 1", "status": "Operando"}, {"id": "EQUIP-PADRAO-LTF-001", "patrimonio": "LTF-001", "nome": "Laminador de Tiras a Frio", "area": "Laminação", "localizacao": "Setor D - Linha 2", "status": "Operando"}, {"id": "EQUIP-PADRAO-PR-001", "patrimonio": "PR-001", "nome": "Ponte Rolante", "area": "Aciaria", "localizacao": "Setor B - Vão 3", "status": "Parado"}, {"id": "EQUIP-PADRAO-FP-002", "patrimonio": "FP-002", "nome": "Forno Panela", "area": "Aciaria", "localizacao": "Setor B - Estação 2", "status": "Manutenção"}];
  for (const area of novasAreas) {
    if (!areas.some(a => String(a.nome || a.area || '').toLowerCase() === area.nome.toLowerCase())) areas.push(area);
  }
  for (const equipamento of novosEquipamentos) {
    if (!equipamentos.some(e => String(e.patrimonio || e.numero_patrimonio || '').toLowerCase() === equipamento.patrimonio.toLowerCase())) equipamentos.push(equipamento);
  }
  localStorage.setItem('autoRelatorio_areas', JSON.stringify(areas));
  localStorage.setItem('autoRelatorio_equipamentos', JSON.stringify(equipamentos));
  localStorage.setItem(marcador, '1');
})();
