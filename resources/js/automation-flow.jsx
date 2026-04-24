import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  ReactFlow,
  Background,
  Controls,
  MiniMap,
  addEdge,
  useNodesState,
  useEdgesState,
  Handle,
  Position,
  useReactFlow,
  ReactFlowProvider,
  Panel,
  MarkerType,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';

// ── Config from Laravel ──────────────────────────────────────────
const cfg             = window.AUTOMATION_FLOW || {};
const FLOW_DATA_URL   = cfg.flowDataUrl    || '';
const FLOW_UPDATE_URL = cfg.flowUpdateUrl  || '';
const FLOW_TEST_URL    = cfg.flowTestUrl    || '';
const UPLOAD_MEDIA_URL = cfg.uploadMediaUrl || '';
const CSRF             = cfg.csrfToken      || '';
const LISTAS          = cfg.listas         || [];
const TAGS            = cfg.tags           || [];
const CUSTOM_FIELDS   = cfg.customFields   || [];
const AUTOMATIONS     = cfg.automations    || [];
const CONTACTS        = cfg.contacts       || [];
const CURRENT_AUTO_ID = cfg.automationId   || null;


// ── Node visual definitions ──────────────────────────────────────
const META = {
  start:          { label: 'Gatilho',              bg: '#ecfdf5', bd: '#6ee7b7', ic: '#10b981', tx: '#065f46', cat: 'Entrada' },
  send_message:   { label: 'Enviar mensagem',     bg: '#f0f9ff', bd: '#7dd3fc', ic: '#0ea5e9', tx: '#0c4a6e', cat: 'Mensagens' },
  condition:      { label: 'Condição (Se/Senão)', bg: '#fafaf9', bd: '#a8a29e', ic: '#78716c', tx: '#1c1917', cat: 'Controle' },
  delay:          { label: 'Aguardar',            bg: '#fffbeb', bd: '#fcd34d', ic: '#d97706', tx: '#78350f', cat: 'Controle' },
  go_to:          { label: 'Ir para fluxo',       bg: '#eff6ff', bd: '#93c5fd', ic: '#3b82f6', tx: '#1e3a5f', cat: 'Controle' },
  user_input:     { label: 'Entrada do usuário',  bg: '#fff7ed', bd: '#fdba74', ic: '#f97316', tx: '#7c2d12', cat: 'Dados' },
  update_field:   { label: 'Atualizar campo',     bg: '#f0fdf4', bd: '#86efac', ic: '#22c55e', tx: '#14532d', cat: 'Dados' },
  add_tag:        { label: 'Adicionar tag',       bg: '#f5f3ff', bd: '#c4b5fd', ic: '#7c3aed', tx: '#4c1d95', cat: 'Contatos' },
  remove_tag:     { label: 'Remover tag',         bg: '#fef2f2', bd: '#fca5a5', ic: '#dc2626', tx: '#7f1d1d', cat: 'Contatos' },
  add_list:       { label: 'Adicionar à lista',   bg: '#eef2ff', bd: '#a5b4fc', ic: '#4f46e5', tx: '#1e1b4b', cat: 'Contatos' },
  remove_list:    { label: 'Remover da lista',    bg: '#fff1f2', bd: '#fda4af', ic: '#e11d48', tx: '#881337', cat: 'Contatos' },
  human_transfer: { label: 'Transferir humano',   bg: '#fdf2f8', bd: '#f0abfc', ic: '#a21caf', tx: '#701a75', cat: 'Ações' },
};

// Palette definition with categories
const PALETTE_CATS = [
  {
    label: 'Entrada',
    items: [{ type: 'start', desc: 'Define quando a jornada começa' }],
  },
  {
    label: 'Mensagens',
    items: [{ type: 'send_message', desc: 'Texto, imagem, áudio, vídeo, botões...' }],
  },
  {
    label: 'Controle',
    items: [
      { type: 'condition', desc: 'Bifurca o fluxo com Se/Senão' },
      { type: 'delay',     desc: 'Pausa antes do próximo passo' },
      { type: 'go_to',     desc: 'Salta para outra automação' },
    ],
  },
  {
    label: 'Dados',
    items: [
      { type: 'user_input',   desc: 'Aguarda resposta do usuário' },
      { type: 'update_field', desc: 'Atualiza campo do contato' },
    ],
  },
  {
    label: 'Contatos',
    items: [
      { type: 'add_tag',     desc: 'Adiciona etiqueta ao contato' },
      { type: 'remove_tag',  desc: 'Remove etiqueta do contato' },
      { type: 'add_list',    desc: 'Adiciona contato a uma lista' },
      { type: 'remove_list', desc: 'Remove contato de uma lista' },
    ],
  },
  {
    label: 'Ações',
    items: [{ type: 'human_transfer', desc: 'Transfere para atendente humano' }],
  },
];

// ── SVG icon paths ───────────────────────────────────────────────
const ICONS = {
  start:          [['M13 10V3L4 14h7v7l9-11h-7z']],
  send_message:   [['M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z']],
  condition:      [['M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7']],
  delay:          [['M12 8v4l3 3'], ['M21 12a9 9 0 11-18 0 9 9 0 0118 0z']],
  go_to:          [['M13 9l3 3m0 0l-3 3m3-3H8m13 0a9 9 0 11-18 0 9 9 0 0118 0z']],
  user_input:     [['M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z']],
  update_field:   [['M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z']],
  add_tag:        [['M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z']],
  remove_tag:     [['M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z'], ['M18 6L6 18']],
  add_list:       [['M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4']],
  remove_list:    [['M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'], ['M10 12l4 4m0-4l-4 4']],
  human_transfer: [['M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z']],
};

function NodeIcon({ type, size = 16, color = '#fff' }) {
  const groups = ICONS[type] || [];
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none"
      stroke={color} strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
      {groups.map((paths, gi) =>
        paths.map((d, pi) => <path key={`${gi}-${pi}`} d={d} />)
      )}
    </svg>
  );
}

// ── Node summary text ────────────────────────────────────────────
const MSG_TYPE_LABELS = {
  text: 'Texto', image: 'Imagem', audio: 'Áudio', video: 'Vídeo',
  document: 'Documento', buttons: 'Botões', list: 'Lista',
};

const COND_OP_LABELS = {
  equals: '=', not_equals: '≠', contains: 'contém', not_contains: '!contém',
  is_empty: 'vazio', is_not_empty: 'preenchido',
  starts_with: 'começa com', ends_with: 'termina com',
  has_tag: 'tem tag', not_has_tag: 'não tem tag',
};

const ATTR_LABELS = { name: 'Nome', email: 'E-mail', phone: 'Telefone' };

const TRIGGER_TYPE_LABELS = {
  tag_added:  'Quando receber uma tag',
  list_added: 'Quando adicionado a uma lista',
};

function getNodeSummary(type, config) {
  if (!config) return '';
  switch (type) {
    case 'start': {
      const tt = config.trigger_type;
      if (!tt) return '';
      const freq = config.run_once_per_contact === false ? ' · 🔁' : ' · 1×';
      if (tt === 'tag_added') {
        const t = TAGS.find(t => String(t.id) === String(config.tag_id));
        return (t ? `Tag: ${t.name}` : 'Tag: —') + freq;
      }
      if (tt === 'list_added') {
        const l = LISTAS.find(l => String(l.id) === String(config.lista_id));
        return (l ? `Lista: ${l.name}` : 'Lista: —') + freq;
      }
      return (TRIGGER_TYPE_LABELS[tt] || tt) + freq;
    }
    case 'send_message': {
      const mt = config.message_type || 'text';
      if (mt === 'text') return config.message ? config.message.slice(0, 55) + (config.message.length > 55 ? '…' : '') : '';
      const label = MSG_TYPE_LABELS[mt] || mt;
      return config.caption ? `${label} · ${config.caption.slice(0, 30)}` : label;
    }
    case 'condition': {
      const ft = config.field_type;
      const op = COND_OP_LABELS[config.operator] || config.operator || '';
      if (ft === 'tag') {
        const t = TAGS.find(t => String(t.id) === String(config.tag_id));
        return t ? `Tag "${t.name}" ${op}` : op;
      }
      if (ft === 'attribute') {
        const f = ATTR_LABELS[config.field_key] || config.field_key || '';
        return `${f} ${op} ${config.value || ''}`.trim();
      }
      if (ft === 'custom') {
        const cf = CUSTOM_FIELDS.find(f => String(f.id) === String(config.contact_field_id));
        return cf ? `${cf.name} ${op} ${config.value || ''}`.trim() : op;
      }
      return '';
    }
    case 'delay': {
      const m = Number(config.minutes) || 0;
      if (m >= 1440) return `${Math.floor(m / 1440)} dia(s)`;
      if (m >= 60)   return `${Math.floor(m / 60)} hora(s)`;
      return m ? `${m} minuto(s)` : '';
    }
    case 'user_input':
      return config.question ? config.question.slice(0, 50) + (config.question.length > 50 ? '…' : '') : '';
    case 'update_field': {
      const ft = config.field_type;
      if (ft === 'attribute') return `${ATTR_LABELS[config.field_key] || ''} = "${config.value || ''}"`;
      const cf = CUSTOM_FIELDS.find(f => String(f.id) === String(config.contact_field_id));
      return cf ? `${cf.name} = "${config.value || ''}"` : '';
    }
    case 'go_to': {
      const a = AUTOMATIONS.find(a => String(a.id) === String(config.automation_id));
      return a ? `→ ${a.name}` : '';
    }
    case 'human_transfer':
      return config.message ? config.message.slice(0, 50) + (config.message.length > 50 ? '…' : '') : 'Transferir para atendente';
    case 'add_tag':
    case 'remove_tag': {
      const t = TAGS.find(t => String(t.id) === String(config.tag_id));
      return t ? t.name : '';
    }
    case 'add_list':
    case 'remove_list': {
      const l = LISTAS.find(l => String(l.id) === String(config.lista_id));
      return l ? l.name : '';
    }
    default: return '';
  }
}

// ── Test result badge config ─────────────────────────────────────
const TEST_BADGE = {
  start:         { bg: '#dcfce7', tx: '#166534', border: '#16a34a', icon: '▶', label: 'Entrada'              },
  success:       { bg: '#dcfce7', tx: '#166534', border: '#16a34a', icon: '✓', label: 'Executado'            },
  simulated:     { bg: '#f1f5f9', tx: '#475569', border: '#94a3b8', icon: '○', label: 'Simulado'             },
  error:         { bg: '#fee2e2', tx: '#991b1b', border: '#dc2626', icon: '✗', label: 'Erro'                 },
  condition_yes: { bg: '#dcfce7', tx: '#166534', border: '#16a34a', icon: '✓', label: 'Ramo: Sim'            },
  condition_no:  { bg: '#ffedd5', tx: '#9a3412', border: '#ea580c', icon: '✗', label: 'Ramo: Não'            },
  delay:         { bg: '#fef9c3', tx: '#854d0e', border: '#ca8a04', icon: '⏰', label: 'Delay agendado'      },
  delay_skipped: { bg: '#dbeafe', tx: '#1e40af', border: '#3b82f6', icon: '⏭', label: 'Delay (ignorado no teste)' },
  waiting:       { bg: '#fef9c3', tx: '#854d0e', border: '#ca8a04', icon: '⌨', label: 'Aguardando resposta' },
};

// ── Custom Node ──────────────────────────────────────────────────
function AutomationNode({ id, type, data, selected }) {
  const { setNodes, setEdges } = useReactFlow();
  const [hovered, setHovered] = useState(false);
  const meta       = META[type] || { label: type, bg: '#f9fafb', bd: '#d1d5db', ic: '#6b7280', tx: '#111827' };
  const summary    = getNodeSummary(type, data.config);
  const isCond     = type === 'condition';
  const testDetail = data.testResult || null;  // set by FlowEditorInner after test run
  const isLoading  = data.testLoading || false;

  const onDelete = useCallback((e) => {
    e.stopPropagation();
    setNodes(ns => ns.filter(n => n.id !== id));
    setEdges(es => es.filter(e => e.source !== id && e.target !== id));
  }, [id, setNodes, setEdges]);

  // Compute test badge
  let testBadgeKey = null;
  if (testDetail) {
    if (testDetail.action === 'start')           testBadgeKey = 'start';
    else if (testDetail.action === 'condition')  testBadgeKey = testDetail.branch === 'yes' ? 'condition_yes' : 'condition_no';
    else if (testDetail.action === 'delay')      testBadgeKey = testDetail.skipped_in_test ? 'delay_skipped' : 'delay';
    else if (testDetail.action === 'user_input') testBadgeKey = 'waiting';
    else if (testDetail.dry_run)                 testBadgeKey = 'simulated';
    else testBadgeKey = testDetail.success === false ? 'error' : 'success';
  }
  const badge = testBadgeKey ? TEST_BADGE[testBadgeKey] : null;

  const testBorderColor = badge ? badge.border : null;

  const borderColor = testBorderColor || (selected ? meta.ic : hovered ? `${meta.ic}99` : meta.bd);
  const shadow = testBorderColor
    ? `0 0 0 3px ${testBorderColor}35, 0 4px 20px ${testBorderColor}25`
    : selected
      ? `0 0 0 3px ${meta.ic}28, 0 4px 20px rgba(0,0,0,0.14)`
      : hovered ? '0 4px 14px rgba(0,0,0,0.11)' : '0 2px 8px rgba(0,0,0,0.07)';

  return (
    <div
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
      style={{
        background: meta.bg,
        border: `2px solid ${borderColor}`,
        borderRadius: 14,
        minWidth: isCond ? 232 : 214,
        boxShadow: shadow,
        transition: 'border-color 0.2s, box-shadow 0.2s',
        position: 'relative',
        userSelect: 'none',
        opacity: isLoading ? 0.65 : 1,
      }}
    >
      {/* Loading scan line */}
      {isLoading && (
        <div style={{
          position: 'absolute', inset: 0, borderRadius: 12, overflow: 'hidden',
          background: 'linear-gradient(90deg, transparent 0%, rgba(16,185,129,.18) 50%, transparent 100%)',
          backgroundSize: '200% 100%',
          animation: 'scanLine 1.2s linear infinite',
          zIndex: 5, pointerEvents: 'none',
        }} />
      )}

      {/* Delete btn */}
      <div onClick={onDelete} title="Remover" style={{
        position: 'absolute', top: -11, right: -11,
        width: 22, height: 22, borderRadius: '50%',
        background: '#ef4444', color: '#fff', cursor: 'pointer',
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        fontSize: 15, fontWeight: 700,
        boxShadow: '0 2px 6px rgba(239,68,68,.4)',
        opacity: hovered || selected ? 1 : 0,
        transition: 'opacity 0.15s', zIndex: 10,
      }}>×</div>

      {/* Input handle */}
      {type !== 'start' && (
        <Handle type="target" position={Position.Left} id="input"
          style={{ background: meta.ic, width: 10, height: 10, border: '2.5px solid #fff', left: -6 }} />
      )}

      {/* Header */}
      <div style={{ padding: '11px 14px 8px', display: 'flex', alignItems: 'flex-start', gap: 10 }}>
        <div style={{
          width: 32, height: 32, borderRadius: 9, background: meta.ic,
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          flexShrink: 0, marginTop: 1,
        }}>
          <NodeIcon type={type} size={15} />
        </div>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontSize: 12.5, fontWeight: 700, color: meta.tx, lineHeight: 1.3 }}>
            {meta.label}
          </div>
          {summary ? (
            <div style={{ fontSize: 11.5, color: '#6b7280', marginTop: 3, lineHeight: 1.45, wordBreak: 'break-word' }}>
              {summary}
            </div>
          ) : (
            <div style={{ fontSize: 11, color: '#b0b8c4', marginTop: 3, fontStyle: 'italic' }}>
              Clique para configurar
            </div>
          )}
        </div>
      </div>

      {/* Condition branch labels */}
      {isCond && (
        <div style={{ borderTop: `1px solid ${meta.bd}`, margin: '0 10px 0', padding: '6px 0 8px' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 5, paddingRight: 18 }}>
            <div style={{ width: 14, height: 14, borderRadius: '50%', background: '#22c55e', flexShrink: 0, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="3.5"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <span style={{ fontSize: 11, fontWeight: 700, color: '#15803d' }}>Sim (verdadeiro)</span>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 6, paddingRight: 18 }}>
            <div style={{ width: 14, height: 14, borderRadius: '50%', background: '#ef4444', flexShrink: 0, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="3.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
            <span style={{ fontSize: 11, fontWeight: 700, color: '#b91c1c' }}>Não (falso)</span>
          </div>
        </div>
      )}

      {/* Test result status bar (n8n style) */}
      {badge && (
        <div style={{
          margin: '0 0 0 0',
          borderTop: `1px solid ${badge.border}40`,
          background: badge.bg,
          borderBottomLeftRadius: 12, borderBottomRightRadius: 12,
          padding: '5px 12px',
          display: 'flex', alignItems: 'center', gap: 6,
        }}>
          <span style={{ fontSize: 12, lineHeight: 1 }}>{badge.icon}</span>
          <span style={{ fontSize: 11, fontWeight: 700, color: badge.tx, flex: 1 }}>
            {badge.label}
          </span>
          {testDetail?.reason && (
            <span title={testDetail.reason} style={{ fontSize: 10, color: '#dc2626', maxWidth: 100, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
              {testDetail.reason}
            </span>
          )}
        </div>
      )}

      {/* Output handle(s) */}
      {isCond ? (
        <>
          <Handle type="source" id="yes" position={Position.Right}
            style={{ background: '#22c55e', width: 11, height: 11, border: '2.5px solid #fff', top: '68%' }} />
          <Handle type="source" id="no" position={Position.Right}
            style={{ background: '#ef4444', width: 11, height: 11, border: '2.5px solid #fff', top: '86%' }} />
        </>
      ) : (
        <Handle type="source" position={Position.Right} id="default"
          style={{ background: meta.ic, width: 10, height: 10, border: '2.5px solid #fff', right: -6 }} />
      )}
    </div>
  );
}

const nodeTypes = Object.fromEntries(Object.keys(META).map(t => [t, AutomationNode]));

// ── Test Panel ───────────────────────────────────────────────────
const ACTION_LABELS = {
  start: 'Gatilho', send_message: 'Enviar mensagem', condition: 'Condição',
  delay: 'Aguardar', go_to: 'Ir para fluxo', user_input: 'Entrada do usuário',
  update_field: 'Atualizar campo', add_tag: 'Adicionar tag', remove_tag: 'Remover tag',
  add_list: 'Adicionar à lista', remove_list: 'Remover da lista', human_transfer: 'Transferir humano',
};

function TestPanel({ onClose, onResults, onLoading }) {
  const [contactId, setContactId] = useState('');
  const [loading,   setLoading]   = useState(false);
  const [result,    setResult]    = useState(null);

  const run = async () => {
    if (!contactId || !FLOW_TEST_URL) return;
    setLoading(true);
    onLoading(true);
    setResult(null);
    onResults([]);
    try {
      const res  = await fetch(FLOW_TEST_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ contact_id: Number(contactId) }),
      });
      const data = await res.json();
      setResult(data);
      if (data.details) onResults(data.details);
    } catch {
      setResult({ ok: false, success: false, message: 'Erro de conexão.' });
      onResults([]);
    } finally {
      setLoading(false);
      onLoading(false);
    }
  };

  return (
    <div style={{
      width: 270, background: '#fff', borderRadius: 14,
      boxShadow: '0 8px 32px rgba(0,0,0,.18)', border: '1px solid #e2e8f0',
      overflow: 'hidden', display: 'flex', flexDirection: 'column',
    }}>
      {/* Header */}
      <div style={{ padding: '10px 14px', background: '#f8fafc', borderBottom: '1px solid #e2e8f0', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 7 }}>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10b981" strokeWidth="2.5">
            <path d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
            <path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <span style={{ fontSize: 12.5, fontWeight: 700, color: '#1e293b' }}>Testar flow</span>
        </div>
        <button onClick={onClose} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#94a3b8', fontSize: 18, lineHeight: 1, padding: 2 }}>×</button>
      </div>

      <div style={{ padding: '10px 14px', borderBottom: '1px solid #f1f5f9' }}>
        <div style={{ fontSize: 11, color: '#64748b', marginBottom: 6 }}>Contato</div>
        <select value={contactId} onChange={e => { setContactId(e.target.value); setResult(null); onResults([]); }}
          style={{ width: '100%', borderRadius: 7, border: '1px solid #d1d5db', padding: '6px 8px', fontSize: 12, outline: 'none', color: '#111827', cursor: 'pointer' }}>
          <option value="">— Selecionar —</option>
          {CONTACTS.map(c => (
            <option key={c.id} value={c.id}>{c.name}{c.phone ? ` · ${c.phone}` : ''}</option>
          ))}
        </select>
        <button onClick={run} disabled={!contactId || loading}
          style={{
            marginTop: 8, width: '100%', padding: '7px 0', borderRadius: 7, border: 'none',
            background: !contactId || loading ? '#d1fae5' : '#10b981', color: '#fff',
            fontSize: 12.5, fontWeight: 700, cursor: !contactId || loading ? 'default' : 'pointer',
            display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6,
          }}>
          {loading
            ? <><span style={{ display: 'inline-block', width: 13, height: 13, border: '2px solid #fff4', borderTopColor: '#fff', borderRadius: '50%', animation: 'spin .7s linear infinite' }} /> Executando…</>
            : '▶ Executar teste'
          }
        </button>
      </div>

      {/* Results */}
      {result && (
        <div style={{ maxHeight: 320, overflowY: 'auto' }}>
          {/* Summary */}
          <div style={{
            padding: '8px 14px', fontSize: 11.5, fontWeight: 700,
            background: result.success ? '#f0fdf4' : '#fef2f2',
            color: result.success ? '#065f46' : '#991b1b',
            borderBottom: '1px solid #f1f5f9',
          }}>
            {result.success ? '✓' : '✗'} {result.message}
          </div>

          {/* Step list */}
          {(result.details || []).map((d, i) => {
            let badgeKey = 'success';
            if (d.action === 'start')          badgeKey = 'start';
            else if (d.action === 'condition') badgeKey = d.branch === 'yes' ? 'condition_yes' : 'condition_no';
            else if (d.action === 'delay')     badgeKey = d.skipped_in_test ? 'delay_skipped' : 'delay';
            else if (d.action === 'user_input') badgeKey = 'waiting';
            else if (d.dry_run)               badgeKey = 'simulated';
            else if (d.success === false)      badgeKey = 'error';
            const badge = TEST_BADGE[badgeKey];
            return (
              <div key={i} style={{ padding: '6px 14px', borderBottom: '1px solid #f8fafc', display: 'flex', alignItems: 'center', gap: 8 }}>
                <div style={{ width: 6, height: 6, borderRadius: '50%', background: badge.bg, flexShrink: 0 }} />
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontSize: 11.5, fontWeight: 600, color: '#374151' }}>
                    {ACTION_LABELS[d.action] || d.action}
                  </div>
                  {d.action === 'condition' && (
                    <div style={{ fontSize: 10.5, color: badge.bg }}>Tomou o ramo: {d.branch === 'yes' ? 'Sim ✓' : 'Não ✗'}</div>
                  )}
                  {d.action === 'delay' && (
                    <div style={{ fontSize: 10.5, color: '#92400e' }}>Aguardando {d.scheduled_after_minutes} min</div>
                  )}
                  {d.reason && (
                    <div style={{ fontSize: 10.5, color: '#dc2626', marginTop: 1 }}>{d.reason}</div>
                  )}
                </div>
                <div style={{ fontSize: 10, fontWeight: 700, color: badge.bg, flexShrink: 0 }}>{badge.label}</div>
              </div>
            );
          })}

          <div style={{ padding: '6px 14px 10px' }}>
            <button onClick={() => { setResult(null); onResults([]); onLoading(false); }}
              style={{ fontSize: 11, color: '#94a3b8', background: 'none', border: 'none', cursor: 'pointer', padding: 0 }}>
              Limpar resultado
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

// ── Node Palette ─────────────────────────────────────────────────
function PaletteItem({ type, desc, meta, onDragStart, onAddNode }) {
  const [h, setH] = useState(false);
  return (
    <div
      draggable
      onDragStart={e => onDragStart(e, type)}
      onClick={() => onAddNode(type)}
      onMouseEnter={() => setH(true)}
      onMouseLeave={() => setH(false)}
      style={{
        display: 'flex', alignItems: 'center', gap: 9,
        padding: '6px 8px', borderRadius: 8, marginBottom: 2,
        cursor: 'grab',
        background: h ? meta.bg : 'transparent',
        border: `1px solid ${h ? meta.bd : 'transparent'}`,
        transition: 'background .12s, border-color .12s',
        userSelect: 'none',
      }}
    >
      <div style={{
        width: 26, height: 26, borderRadius: 7, background: meta.ic,
        display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0,
      }}>
        <NodeIcon type={type} size={13} />
      </div>
      <div style={{ minWidth: 0 }}>
        <div style={{ fontSize: 11.5, fontWeight: 600, color: '#374151', lineHeight: 1.2 }}>{meta.label}</div>
        <div style={{ fontSize: 10, color: '#9ca3af', marginTop: 1 }}>{desc}</div>
      </div>
    </div>
  );
}

function NodePalette({ onAddNode }) {
  const onDragStart = (e, type) => {
    e.dataTransfer.setData('application/rf-type', type);
    e.dataTransfer.effectAllowed = 'move';
  };
  return (
    <div style={{
      width: 195, flexShrink: 0, background: '#fff',
      borderRight: '1px solid #e9ecef',
      overflowY: 'auto', display: 'flex', flexDirection: 'column',
    }}>
      <div style={{ padding: '12px 12px 6px', borderBottom: '1px solid #f3f4f6' }}>
        <div style={{ fontSize: 10, fontWeight: 800, textTransform: 'uppercase', letterSpacing: '.08em', color: '#94a3b8' }}>Blocos</div>
        <div style={{ fontSize: 10, color: '#c4cdd6', marginTop: 2 }}>Arraste ou clique para adicionar</div>
      </div>
      <div style={{ padding: '6px 8px', flex: 1 }}>
        {PALETTE_CATS.map(cat => (
          <div key={cat.label} style={{ marginBottom: 8 }}>
            <div style={{ fontSize: 9.5, fontWeight: 800, textTransform: 'uppercase', letterSpacing: '.06em', color: '#cbd5e1', padding: '4px 2px 2px' }}>
              {cat.label}
            </div>
            {cat.items.map(({ type, desc }) => (
              <PaletteItem key={type} type={type} desc={desc} meta={META[type]} onDragStart={onDragStart} onAddNode={onAddNode} />
            ))}
          </div>
        ))}
      </div>
    </div>
  );
}

// ── Shared input styles ──────────────────────────────────────────
const INPUT = {
  width: '100%', borderRadius: 8, border: '1px solid #d1d5db',
  padding: '8px 10px', fontSize: 13, outline: 'none',
  boxSizing: 'border-box', fontFamily: 'inherit', lineHeight: 1.5, color: '#111827',
};

// ── Entry Condition Row ──────────────────────────────────────────
const ENTRY_COND_OPS_ATTR = {
  equals: 'igual a', not_equals: 'diferente de', contains: 'contém',
  is_empty: 'está vazio', is_not_empty: 'não está vazio',
};
const ENTRY_COND_OPS_MSG = {
  is_sent: 'foi enviada', is_delivered: 'foi entregue', is_read: 'foi lida',
  is_not_delivered: 'não foi entregue', is_not_read: 'não foi lida',
};

function EntryConditionRow({ cond, onChange, onRemove }) {
  const needsValue = !['is_empty', 'is_not_empty', 'is_sent', 'is_delivered', 'is_read', 'is_not_delivered', 'is_not_read'].includes(cond.operator);
  const ops = cond.field_type === 'message_status' ? ENTRY_COND_OPS_MSG : ENTRY_COND_OPS_ATTR;

  return (
    <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8, padding: '8px 10px', marginBottom: 6, position: 'relative' }}>
      <button onClick={onRemove} style={{ position: 'absolute', top: 6, right: 8, background: 'none', border: 'none', color: '#94a3b8', cursor: 'pointer', fontSize: 16, lineHeight: 1, padding: 0 }}>×</button>

      <div style={{ fontSize: 11, fontWeight: 600, color: '#64748b', marginBottom: 6 }}>Verificar</div>
      <select value={cond.field_type} onChange={e => onChange({ ...cond, field_type: e.target.value, operator: 'equals', field_key: 'name', contact_field_id: null, value: '' })}
        style={{ ...INPUT, marginBottom: 6, fontSize: 12 }}>
        <option value="attribute">Atributo do contato</option>
        <option value="custom">Campo personalizado</option>
        <option value="message_status">Status da última mensagem</option>
      </select>

      {cond.field_type === 'attribute' && (
        <select value={cond.field_key || 'name'} onChange={e => onChange({ ...cond, field_key: e.target.value })}
          style={{ ...INPUT, marginBottom: 6, fontSize: 12 }}>
          <option value="name">Nome</option>
          <option value="email">E-mail</option>
          <option value="phone">Telefone</option>
        </select>
      )}
      {cond.field_type === 'custom' && (
        <select value={String(cond.contact_field_id || '')} onChange={e => onChange({ ...cond, contact_field_id: e.target.value || null })}
          style={{ ...INPUT, marginBottom: 6, fontSize: 12 }}>
          <option value="">— Campo —</option>
          {CUSTOM_FIELDS.map(f => <option key={f.id} value={f.id}>{f.name}</option>)}
        </select>
      )}

      <select value={cond.operator || 'equals'} onChange={e => onChange({ ...cond, operator: e.target.value })}
        style={{ ...INPUT, marginBottom: needsValue ? 6 : 0, fontSize: 12 }}>
        {Object.entries(ops).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
      </select>

      {needsValue && cond.field_type !== 'message_status' && (
        <input value={cond.value || ''} onChange={e => onChange({ ...cond, value: e.target.value })}
          placeholder='Valor...' style={{ ...INPUT, fontSize: 12 }} />
      )}
    </div>
  );
}

// ── Media Upload Field ───────────────────────────────────────────
const ACCEPT_MAP = {
  image:    'image/jpeg,image/png,image/gif,image/webp',
  video:    'video/mp4,video/mov,video/avi',
  audio:    'audio/mp3,audio/ogg,audio/wav,audio/m4a,audio/mpeg',
  document: '.pdf,.doc,.docx,.xls,.xlsx,.zip',
};

function MediaUploadField({ msgType, mediaUrl, setMediaUrl, filename, setFilename }) {
  const [uploading, setUploading]   = useState(false);
  const [uploadErr, setUploadErr]   = useState('');
  const fileRef                     = useRef(null);

  const isImage = msgType === 'image';

  const handleFile = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setUploading(true);
    setUploadErr('');
    try {
      const form = new FormData();
      form.append('file', file);
      const res  = await fetch(UPLOAD_MEDIA_URL, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        body: form,
      });
      const data = await res.json();
      if (data.ok) {
        setMediaUrl(data.url);
        if (msgType === 'document' && data.filename) setFilename(data.filename);
      } else {
        setUploadErr(data.message || 'Erro no upload');
      }
    } catch {
      setUploadErr('Erro de conexão');
    } finally {
      setUploading(false);
      e.target.value = '';
    }
  };

  return (
    <div>
      {/* Preview de imagem */}
      {isImage && mediaUrl && (
        <div style={{ marginTop: 10, marginBottom: 8, borderRadius: 8, overflow: 'hidden', border: '1px solid #e5e7eb', background: '#f9fafb' }}>
          <img src={mediaUrl} alt="preview"
            style={{ width: '100%', maxHeight: 140, objectFit: 'cover', display: 'block' }}
            onError={e => { e.target.style.display = 'none'; }}
          />
        </div>
      )}

      {/* Área de upload */}
      <div style={{ marginTop: 10 }}>
        <div style={{ fontSize: 11.5, fontWeight: 600, color: '#374151', marginBottom: 6 }}>Arquivo de mídia</div>

        {/* Drop zone / upload button */}
        <div
          onClick={() => !uploading && fileRef.current?.click()}
          style={{
            border: '2px dashed #d1d5db', borderRadius: 10, padding: '14px 12px',
            textAlign: 'center', cursor: uploading ? 'default' : 'pointer',
            background: '#f9fafb', transition: 'border-color .15s',
          }}
          onMouseEnter={e => { if (!uploading) e.currentTarget.style.borderColor = '#6ee7b7'; }}
          onMouseLeave={e => { e.currentTarget.style.borderColor = '#d1d5db'; }}
        >
          {uploading ? (
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8 }}>
              <div style={{ width: 16, height: 16, border: '2px solid #d1fae5', borderTopColor: '#10b981', borderRadius: '50%', animation: 'spin .7s linear infinite' }} />
              <span style={{ fontSize: 12, color: '#6b7280' }}>Enviando...</span>
            </div>
          ) : (
            <>
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="1.5" style={{ margin: '0 auto 6px' }}>
                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/>
                <line x1="12" y1="3" x2="12" y2="15"/>
              </svg>
              <div style={{ fontSize: 12, fontWeight: 600, color: '#374151' }}>Clique para selecionar arquivo</div>
              <div style={{ fontSize: 11, color: '#9ca3af', marginTop: 2 }}>
                {msgType === 'image'    && 'JPG, PNG, GIF, WEBP — até 20MB'}
                {msgType === 'video'    && 'MP4, MOV, AVI — até 20MB'}
                {msgType === 'audio'    && 'MP3, OGG, WAV, M4A — até 20MB'}
                {msgType === 'document' && 'PDF, DOC, XLS, ZIP — até 20MB'}
              </div>
            </>
          )}
        </div>

        <input ref={fileRef} type="file" accept={ACCEPT_MAP[msgType] || '*/*'}
          onChange={handleFile} style={{ display: 'none' }} />

        {uploadErr && (
          <div style={{ marginTop: 6, fontSize: 11, color: '#dc2626' }}>{uploadErr}</div>
        )}
      </div>

      {/* URL manual (separador) */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '10px 0 6px' }}>
        <div style={{ flex: 1, height: 1, background: '#f1f5f9' }} />
        <span style={{ fontSize: 10.5, color: '#9ca3af', whiteSpace: 'nowrap' }}>ou cole a URL</span>
        <div style={{ flex: 1, height: 1, background: '#f1f5f9' }} />
      </div>

      <input
        value={mediaUrl}
        onChange={e => setMediaUrl(e.target.value)}
        placeholder="https://..."
        style={INPUT}
      />

      {msgType === 'document' && (
        <>
          <div style={{ fontSize: 11.5, fontWeight: 600, color: '#374151', marginBottom: 5, marginTop: 10 }}>Nome do arquivo</div>
          <input value={filename} onChange={e => setFilename(e.target.value)} placeholder="documento.pdf" style={INPUT} />
        </>
      )}
    </div>
  );
}

// ── Properties Panel ─────────────────────────────────────────────
function PropertiesPanel({ node, onUpdate, onClose }) {
  const { type, data } = node;
  const config = data.config || {};
  const meta = META[type] || {};

  // ── start (trigger) state ────────────────────────────────────
  const [triggerType,       setTriggerType]       = useState(config.trigger_type         || '');
  const [triggerTagId,      setTriggerTagId]       = useState(String(config.tag_id        || ''));
  const [triggerListaId,    setTriggerListaId]     = useState(String(config.lista_id      || ''));
  const [runOnce,           setRunOnce]            = useState(config.run_once_per_contact !== false);
  const [condLogic,         setCondLogic]          = useState(config.condition_logic      || 'and');
  const [entryConditions,   setEntryConditions]    = useState(config.conditions           || []);

  // ── send_message state ───────────────────────────────────────
  const [msgType,     setMsgType]     = useState(config.message_type || 'text');
  const [message,     setMessage]     = useState(config.message    || '');
  const [mediaUrl,    setMediaUrl]    = useState(config.media_url  || '');
  const [caption,     setCaption]     = useState(config.caption    || '');
  const [filename,    setFilename]    = useState(config.filename   || '');
  const [buttons,     setButtons]     = useState(config.buttons    || [{ id: '1', text: '' }]);
  const [listBtnText, setListBtnText] = useState(config.list_button_text || 'Ver opções');
  const [listItems,   setListItems]   = useState(
    (config.list_sections?.[0]?.rows) || [{ id: '1', title: '' }]
  );

  // ── condition state ──────────────────────────────────────────
  const [condFieldType, setCondFieldType] = useState(config.field_type    || 'attribute');
  const [condFieldKey,  setCondFieldKey]  = useState(config.field_key     || 'name');
  const [condTagId,     setCondTagId]     = useState(String(config.tag_id || ''));
  const [condFieldId,   setCondFieldId]   = useState(String(config.contact_field_id || ''));
  const [condOperator,  setCondOperator]  = useState(config.operator       || 'equals');
  const [condValue,     setCondValue]     = useState(config.value          || '');

  // ── delay state ──────────────────────────────────────────────
  const rawMin = Number(config.minutes) || 60;
  const [delayUnit,  setDelayUnit]  = useState(() => rawMin >= 1440 && rawMin % 1440 === 0 ? 'days' : rawMin >= 60 && rawMin % 60 === 0 ? 'hours' : 'minutes');
  const [delayValue, setDelayValue] = useState(() => rawMin >= 1440 && rawMin % 1440 === 0 ? rawMin / 1440 : rawMin >= 60 && rawMin % 60 === 0 ? rawMin / 60 : rawMin);

  // ── user_input state ─────────────────────────────────────────
  const [uiQuestion,   setUiQuestion]   = useState(config.question           || '');
  const [uiSaveTo,     setUiSaveTo]     = useState(config.save_to            || 'attribute');
  const [uiAttrKey,    setUiAttrKey]    = useState(config.attribute_key      || 'name');
  const [uiFieldId,    setUiFieldId]    = useState(String(config.contact_field_id || ''));
  const [uiTimeout,    setUiTimeout]    = useState(config.timeout_minutes    || 60);

  // ── update_field state ───────────────────────────────────────
  const [ufFieldType, setUfFieldType] = useState(config.field_type        || 'attribute');
  const [ufAttrKey,   setUfAttrKey]   = useState(config.field_key         || 'name');
  const [ufFieldId,   setUfFieldId]   = useState(String(config.contact_field_id || ''));
  const [ufValue,     setUfValue]     = useState(config.value             || '');

  // ── tag/list state ───────────────────────────────────────────
  const [tagId,   setTagId]   = useState(String(config.tag_id   || ''));
  const [listaId, setListaId] = useState(String(config.lista_id || ''));

  // ── go_to state ──────────────────────────────────────────────
  const [gotoAutoId, setGotoAutoId] = useState(String(config.automation_id || ''));

  // ── human_transfer state ─────────────────────────────────────
  const [htMessage, setHtMessage] = useState(config.message  || '');
  const [htTagId,   setHtTagId]   = useState(String(config.tag_id || ''));

  // ── helpers ──────────────────────────────────────────────────
  const totalMinutes = () => {
    const mult = delayUnit === 'days' ? 1440 : delayUnit === 'hours' ? 60 : 1;
    return Math.max(1, Math.min(10080, Math.round(Number(delayValue) * mult)));
  };

  const addButton  = () => { if (buttons.length < 3) setButtons([...buttons, { id: String(buttons.length + 1), text: '' }]); };
  const rmButton   = (i) => setButtons(buttons.filter((_, idx) => idx !== i));
  const updButton  = (i, text) => setButtons(buttons.map((b, idx) => idx === i ? { ...b, text } : b));
  const addItem    = () => { if (listItems.length < 10) setListItems([...listItems, { id: String(listItems.length + 1), title: '' }]); };
  const rmItem     = (i) => setListItems(listItems.filter((_, idx) => idx !== i));
  const updItem    = (i, title) => setListItems(listItems.map((r, idx) => idx === i ? { ...r, title } : r));

  const addEntryCond = () => setEntryConditions(cs => [...cs, { field_type: 'attribute', field_key: 'name', contact_field_id: null, operator: 'equals', value: '' }]);
  const updEntryCond = (i, val) => setEntryConditions(cs => cs.map((c, idx) => idx === i ? val : c));
  const rmEntryCond  = (i) => setEntryConditions(cs => cs.filter((_, idx) => idx !== i));

  const condNeedsValue = !['is_empty', 'is_not_empty', 'has_tag', 'not_has_tag'].includes(condOperator);
  const condOps = condFieldType === 'tag'
    ? { has_tag: 'possui a tag', not_has_tag: 'não possui a tag' }
    : { equals: 'igual a', not_equals: 'diferente de', contains: 'contém', not_contains: 'não contém', is_empty: 'está vazio', is_not_empty: 'preenchido', starts_with: 'começa com', ends_with: 'termina com' };

  const apply = () => {
    let next = {};

    if (type === 'start') {
      const conds = entryConditions.filter(c => c.field_type && c.operator);
      next = {
        trigger_type:         triggerType    || null,
        tag_id:               triggerTagId   ? Number(triggerTagId)   : null,
        lista_id:             triggerListaId ? Number(triggerListaId) : null,
        run_once_per_contact: runOnce,
        condition_logic:      condLogic,
        conditions:           conds,
      };
    } else if (type === 'send_message') {
      next = { message_type: msgType };
      if (msgType === 'text')     next.message = message;
      if (['image','video','audio','document'].includes(msgType)) {
        next.media_url = mediaUrl;
        if (['image','video'].includes(msgType)) next.caption  = caption;
        if (msgType === 'document') next.filename = filename;
      }
      if (msgType === 'buttons')  next = { ...next, message, buttons: buttons.filter(b => b.text) };
      if (msgType === 'list')     next = { ...next, message, list_button_text: listBtnText,
        list_sections: [{ title: '', rows: listItems.filter(r => r.title).map(r => ({ id: r.id, title: r.title })) }] };
    } else if (type === 'condition') {
      next = { field_type: condFieldType, operator: condOperator, value: condValue };
      if (condFieldType === 'attribute') next.field_key = condFieldKey;
      if (condFieldType === 'tag')       next.tag_id = condTagId ? Number(condTagId) : null;
      if (condFieldType === 'custom')    next.contact_field_id = condFieldId ? Number(condFieldId) : null;
    } else if (type === 'delay') {
      next = { minutes: totalMinutes() };
    } else if (type === 'user_input') {
      next = { question: uiQuestion, save_to: uiSaveTo, timeout_minutes: Number(uiTimeout) || 60 };
      if (uiSaveTo === 'attribute') next.attribute_key = uiAttrKey;
      else next.contact_field_id = uiFieldId ? Number(uiFieldId) : null;
    } else if (type === 'update_field') {
      next = { field_type: ufFieldType, value: ufValue };
      if (ufFieldType === 'attribute') next.field_key = ufAttrKey;
      else next.contact_field_id = ufFieldId ? Number(ufFieldId) : null;
    } else if (type === 'add_tag' || type === 'remove_tag') {
      next = { tag_id: tagId ? Number(tagId) : null };
    } else if (type === 'add_list' || type === 'remove_list') {
      next = { lista_id: listaId ? Number(listaId) : null };
    } else if (type === 'go_to') {
      next = { automation_id: gotoAutoId ? Number(gotoAutoId) : null };
    } else if (type === 'human_transfer') {
      next = { message: htMessage, tag_id: htTagId ? Number(htTagId) : null };
    }

    onUpdate({ ...data, config: next });
    onClose();
  };

  const Label = ({ children }) => (
    <div style={{ fontSize: 11.5, fontWeight: 600, color: '#374151', marginBottom: 5, marginTop: 12 }}>{children}</div>
  );
  const sel = (value, onChange, opts) => (
    <select value={value} onChange={e => onChange(e.target.value)} style={{ ...INPUT, cursor: 'pointer' }}>
      {Object.entries(opts).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
    </select>
  );

  return (
    <div style={{ width: 290, flexShrink: 0, background: '#fff', borderLeft: '1px solid #e9ecef', display: 'flex', flexDirection: 'column', height: '100%' }}>
      {/* Header */}
      <div style={{ padding: '13px 16px', borderBottom: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', gap: 10 }}>
        <div style={{ width: 30, height: 30, borderRadius: 8, background: meta.ic || '#6b7280', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
          <NodeIcon type={type} size={14} />
        </div>
        <div style={{ flex: 1 }}>
          <div style={{ fontSize: 13, fontWeight: 700, color: '#111827' }}>{meta.label || type}</div>
          <div style={{ fontSize: 10.5, color: '#9ca3af' }}>Configurar nó</div>
        </div>
        <button onClick={onClose} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#9ca3af', fontSize: 20, lineHeight: 1, padding: 2 }}>×</button>
      </div>

      {/* Body */}
      <div style={{ padding: '4px 16px 16px', flex: 1, overflowY: 'auto' }}>

        {/* ── START (GATILHO) ── */}
        {type === 'start' && (
          <div>
            <Label>Tipo de gatilho</Label>
            <select value={triggerType} onChange={e => setTriggerType(e.target.value)} style={{ ...INPUT, cursor: 'pointer' }}>
              <option value="">— Selecionar gatilho —</option>
              <option value="tag_added">Quando o contato receber uma tag</option>
              <option value="list_added">Quando o contato for adicionado a uma lista</option>
            </select>

            {triggerType === 'tag_added' && (
              <>
                <Label>Tag</Label>
                {TAGS.length === 0
                  ? <div style={{ fontSize: 12, color: '#9ca3af' }}>Nenhuma tag cadastrada.</div>
                  : <select value={triggerTagId} onChange={e => setTriggerTagId(e.target.value)} style={{ ...INPUT, cursor: 'pointer' }}>
                      <option value="">— Selecionar tag —</option>
                      {TAGS.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                    </select>
                }
              </>
            )}

            {triggerType === 'list_added' && (
              <>
                <Label>Lista</Label>
                {LISTAS.length === 0
                  ? <div style={{ fontSize: 12, color: '#9ca3af' }}>Nenhuma lista cadastrada.</div>
                  : <select value={triggerListaId} onChange={e => setTriggerListaId(e.target.value)} style={{ ...INPUT, cursor: 'pointer' }}>
                      <option value="">— Selecionar lista —</option>
                      {LISTAS.map(l => <option key={l.id} value={l.id}>{l.name}</option>)}
                    </select>
                }
              </>
            )}

            {/* Run once toggle */}
            <div style={{ marginTop: 16, borderTop: '1px solid #f1f5f9', paddingTop: 12 }}>
              <div style={{ fontSize: 11.5, fontWeight: 700, color: '#374151', marginBottom: 8 }}>Frequência de execução</div>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                {[
                  { val: true,  icon: '1️⃣', label: 'Uma vez por contato', desc: 'O contato só entra na jornada uma única vez' },
                  { val: false, icon: '🔁', label: 'Toda vez que o gatilho disparar', desc: 'O contato entra novamente cada vez que o evento ocorrer' },
                ].map(opt => (
                  <div key={String(opt.val)} onClick={() => setRunOnce(opt.val)}
                    style={{
                      display: 'flex', alignItems: 'flex-start', gap: 10, padding: '8px 10px',
                      borderRadius: 8, cursor: 'pointer',
                      border: `1.5px solid ${runOnce === opt.val ? '#10b981' : '#e5e7eb'}`,
                      background: runOnce === opt.val ? '#f0fdf4' : '#f9fafb',
                      transition: 'all .12s',
                    }}>
                    <div style={{
                      width: 16, height: 16, borderRadius: '50%', flexShrink: 0, marginTop: 1,
                      border: `2px solid ${runOnce === opt.val ? '#10b981' : '#d1d5db'}`,
                      background: runOnce === opt.val ? '#10b981' : '#fff',
                      display: 'flex', alignItems: 'center', justifyContent: 'center',
                    }}>
                      {runOnce === opt.val && <div style={{ width: 6, height: 6, borderRadius: '50%', background: '#fff' }} />}
                    </div>
                    <div>
                      <div style={{ fontSize: 11.5, fontWeight: 600, color: runOnce === opt.val ? '#065f46' : '#374151' }}>
                        {opt.icon} {opt.label}
                      </div>
                      <div style={{ fontSize: 10.5, color: '#9ca3af', marginTop: 2 }}>{opt.desc}</div>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Entry conditions */}
            <div style={{ marginTop: 12, borderTop: '1px solid #f1f5f9', paddingTop: 12 }}>
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 8 }}>
                <div style={{ fontSize: 11.5, fontWeight: 700, color: '#374151' }}>Condições de entrada</div>
                <span style={{ fontSize: 10, color: '#9ca3af' }}>opcional</span>
              </div>

              {entryConditions.length > 1 && (
                <div style={{ marginBottom: 8 }}>
                  <div style={{ fontSize: 11, color: '#6b7280', marginBottom: 4 }}>Lógica entre condições</div>
                  <div style={{ display: 'flex', gap: 6 }}>
                    {['and', 'or'].map(v => (
                      <button key={v} onClick={() => setCondLogic(v)} style={{
                        flex: 1, padding: '5px 0', borderRadius: 6, border: `1.5px solid ${condLogic === v ? '#10b981' : '#d1d5db'}`,
                        background: condLogic === v ? '#ecfdf5' : '#fff', color: condLogic === v ? '#065f46' : '#6b7280',
                        fontSize: 12, fontWeight: condLogic === v ? 700 : 400, cursor: 'pointer',
                      }}>
                        {v === 'and' ? 'E (todas)' : 'OU (qualquer)'}
                      </button>
                    ))}
                  </div>
                </div>
              )}

              {entryConditions.map((cond, i) => (
                <EntryConditionRow key={i} cond={cond} onChange={val => updEntryCond(i, val)} onRemove={() => rmEntryCond(i)} />
              ))}

              <button onClick={addEntryCond} style={{ fontSize: 11.5, color: '#10b981', background: 'none', border: '1px dashed #6ee7b7', borderRadius: 6, cursor: 'pointer', padding: '5px 10px', width: '100%', marginTop: 2 }}>
                + Adicionar condição de entrada
              </button>

              {entryConditions.length === 0 && (
                <div style={{ fontSize: 11, color: '#9ca3af', marginTop: 6, lineHeight: 1.5 }}>
                  Sem condições: todos os contatos elegíveis entram na jornada.
                </div>
              )}
            </div>
          </div>
        )}

        {/* ── SEND_MESSAGE ── */}
        {type === 'send_message' && (
          <div>
            <Label>Tipo de mensagem</Label>
            {sel(msgType, setMsgType, { text: '💬 Texto', image: '🖼️ Imagem', audio: '🎵 Áudio', video: '🎬 Vídeo', document: '📄 Documento', buttons: '🔘 Botões', list: '📋 Lista' })}

            {msgType === 'text' && (
              <>
                <Label>Mensagem</Label>
                <textarea value={message} onChange={e => setMessage(e.target.value)} rows={5} placeholder="Digite a mensagem..." style={{ ...INPUT, resize: 'vertical' }} />
                <div style={{ fontSize: 10.5, color: '#9ca3af', marginTop: 4 }}>Variáveis: {`{{name}}`} {`{{phone}}`} {`{{email}}`}</div>
              </>
            )}

            {['image', 'video', 'audio', 'document'].includes(msgType) && (
              <MediaUploadField
                msgType={msgType}
                mediaUrl={mediaUrl}
                setMediaUrl={setMediaUrl}
                filename={filename}
                setFilename={setFilename}
              />
            )}
            {['image', 'video'].includes(msgType) && (
              <>
                <Label>Legenda (opcional)</Label>
                <input value={caption} onChange={e => setCaption(e.target.value)} placeholder="Legenda..." style={INPUT} />
              </>
            )}

            {msgType === 'buttons' && (
              <>
                <Label>Mensagem</Label>
                <textarea value={message} onChange={e => setMessage(e.target.value)} rows={3} placeholder="Texto acima dos botões..." style={{ ...INPUT, resize: 'vertical' }} />
                <Label>Botões (máx. 3)</Label>
                {buttons.map((b, i) => (
                  <div key={i} style={{ display: 'flex', gap: 6, marginBottom: 6 }}>
                    <input value={b.text} onChange={e => updButton(i, e.target.value)} placeholder={`Botão ${i + 1}`} style={{ ...INPUT, flex: 1 }} />
                    {buttons.length > 1 && <button onClick={() => rmButton(i)} style={{ background: '#fef2f2', border: '1px solid #fca5a5', borderRadius: 6, color: '#dc2626', cursor: 'pointer', padding: '0 8px', fontSize: 14 }}>×</button>}
                  </div>
                ))}
                {buttons.length < 3 && <button onClick={addButton} style={{ fontSize: 11.5, color: '#0ea5e9', background: 'none', border: 'none', cursor: 'pointer', padding: 0 }}>+ Adicionar botão</button>}
              </>
            )}

            {msgType === 'list' && (
              <>
                <Label>Mensagem</Label>
                <textarea value={message} onChange={e => setMessage(e.target.value)} rows={3} placeholder="Texto acima da lista..." style={{ ...INPUT, resize: 'vertical' }} />
                <Label>Texto do botão da lista</Label>
                <input value={listBtnText} onChange={e => setListBtnText(e.target.value)} placeholder="Ver opções" style={INPUT} />
                <Label>Itens da lista (máx. 10)</Label>
                {listItems.map((r, i) => (
                  <div key={i} style={{ display: 'flex', gap: 6, marginBottom: 6 }}>
                    <input value={r.title} onChange={e => updItem(i, e.target.value)} placeholder={`Item ${i + 1}`} style={{ ...INPUT, flex: 1 }} />
                    {listItems.length > 1 && <button onClick={() => rmItem(i)} style={{ background: '#fef2f2', border: '1px solid #fca5a5', borderRadius: 6, color: '#dc2626', cursor: 'pointer', padding: '0 8px', fontSize: 14 }}>×</button>}
                  </div>
                ))}
                {listItems.length < 10 && <button onClick={addItem} style={{ fontSize: 11.5, color: '#0ea5e9', background: 'none', border: 'none', cursor: 'pointer', padding: 0 }}>+ Adicionar item</button>}
              </>
            )}
          </div>
        )}

        {/* ── CONDITION ── */}
        {type === 'condition' && (
          <div>
            <Label>Verificar</Label>
            {sel(condFieldType, (v) => { setCondFieldType(v); setCondOperator(v === 'tag' ? 'has_tag' : 'equals'); }, { attribute: 'Atributo do contato', tag: 'Tag do contato', custom: 'Campo personalizado' })}

            {condFieldType === 'attribute' && (
              <>
                <Label>Campo</Label>
                {sel(condFieldKey, setCondFieldKey, { name: 'Nome', email: 'E-mail', phone: 'Telefone' })}
              </>
            )}
            {condFieldType === 'tag' && (
              <>
                <Label>Tag</Label>
                {TAGS.length === 0
                  ? <div style={{ fontSize: 12, color: '#9ca3af' }}>Nenhuma tag cadastrada.</div>
                  : <select value={condTagId} onChange={e => setCondTagId(e.target.value)} style={{ ...INPUT, cursor: 'pointer' }}>
                      <option value="">— Selecionar —</option>
                      {TAGS.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                    </select>
                }
              </>
            )}
            {condFieldType === 'custom' && (
              <>
                <Label>Campo personalizado</Label>
                {CUSTOM_FIELDS.length === 0
                  ? <div style={{ fontSize: 12, color: '#9ca3af' }}>Nenhum campo cadastrado.</div>
                  : <select value={condFieldId} onChange={e => setCondFieldId(e.target.value)} style={{ ...INPUT, cursor: 'pointer' }}>
                      <option value="">— Selecionar —</option>
                      {CUSTOM_FIELDS.map(f => <option key={f.id} value={f.id}>{f.name}</option>)}
                    </select>
                }
              </>
            )}

            <Label>Operador</Label>
            {sel(condOperator, setCondOperator, condOps)}

            {condNeedsValue && condFieldType !== 'tag' && (
              <>
                <Label>Valor</Label>
                <input value={condValue} onChange={e => setCondValue(e.target.value)} placeholder='Ex: "João"' style={INPUT} />
              </>
            )}

            <div style={{ marginTop: 14, padding: '8px 10px', background: '#f8fafc', borderRadius: 8, fontSize: 11, color: '#6b7280', lineHeight: 1.5 }}>
              <strong style={{ color: '#374151' }}>Saídas:</strong><br/>
              <span style={{ color: '#15803d' }}>✓ Sim</span> → condição verdadeira<br/>
              <span style={{ color: '#b91c1c' }}>✗ Não</span> → condição falsa
            </div>
          </div>
        )}

        {/* ── DELAY ── */}
        {type === 'delay' && (
          <div>
            <Label>Aguardar por</Label>
            <div style={{ display: 'flex', gap: 8 }}>
              <input type="number" min={1} value={delayValue} onChange={e => setDelayValue(e.target.value)} style={{ ...INPUT, flex: 1, minWidth: 0 }} />
              {sel(delayUnit, setDelayUnit, { minutes: 'Minutos', hours: 'Horas', days: 'Dias' })}
            </div>
            <div style={{ fontSize: 11, color: '#9ca3af', marginTop: 6, background: '#fffbeb', border: '1px solid #fef3c7', borderRadius: 6, padding: '4px 8px' }}>
              ≈ {totalMinutes()} minuto(s) no total
            </div>
          </div>
        )}

        {/* ── USER_INPUT ── */}
        {type === 'user_input' && (
          <div>
            <div style={{ background: '#fff7ed', border: '1px solid #fed7aa', borderRadius: 8, padding: 10, marginTop: 10, fontSize: 11.5, color: '#92400e', lineHeight: 1.5 }}>
              ⚠️ Aguarda resposta do usuário via WhatsApp. Requer configuração de webhook de entrada.
            </div>
            <Label>Pergunta a enviar</Label>
            <textarea value={uiQuestion} onChange={e => setUiQuestion(e.target.value)} rows={4} placeholder="Ex: Qual é o seu nome?" style={{ ...INPUT, resize: 'vertical' }} />
            <Label>Salvar resposta em</Label>
            {sel(uiSaveTo, setUiSaveTo, { attribute: 'Atributo padrão', custom: 'Campo personalizado' })}
            {uiSaveTo === 'attribute' && (
              <>
                <Label>Campo</Label>
                {sel(uiAttrKey, setUiAttrKey, { name: 'Nome', email: 'E-mail', phone: 'Telefone' })}
              </>
            )}
            {uiSaveTo === 'custom' && (
              <>
                <Label>Campo personalizado</Label>
                {CUSTOM_FIELDS.length === 0
                  ? <div style={{ fontSize: 12, color: '#9ca3af' }}>Nenhum campo cadastrado.</div>
                  : <select value={uiFieldId} onChange={e => setUiFieldId(e.target.value)} style={{ ...INPUT, cursor: 'pointer' }}>
                      <option value="">— Selecionar —</option>
                      {CUSTOM_FIELDS.map(f => <option key={f.id} value={f.id}>{f.name}</option>)}
                    </select>
                }
              </>
            )}
            <Label>Tempo limite (minutos)</Label>
            <input type="number" min={1} max={10080} value={uiTimeout} onChange={e => setUiTimeout(e.target.value)} style={INPUT} />
          </div>
        )}

        {/* ── UPDATE_FIELD ── */}
        {type === 'update_field' && (
          <div>
            <Label>Tipo de campo</Label>
            {sel(ufFieldType, setUfFieldType, { attribute: 'Atributo padrão', custom: 'Campo personalizado' })}
            {ufFieldType === 'attribute' && (
              <>
                <Label>Campo</Label>
                {sel(ufAttrKey, setUfAttrKey, { name: 'Nome', email: 'E-mail', phone: 'Telefone' })}
              </>
            )}
            {ufFieldType === 'custom' && (
              <>
                <Label>Campo personalizado</Label>
                {CUSTOM_FIELDS.length === 0
                  ? <div style={{ fontSize: 12, color: '#9ca3af' }}>Nenhum campo cadastrado.</div>
                  : <select value={ufFieldId} onChange={e => setUfFieldId(e.target.value)} style={{ ...INPUT, cursor: 'pointer' }}>
                      <option value="">— Selecionar —</option>
                      {CUSTOM_FIELDS.map(f => <option key={f.id} value={f.id}>{f.name}</option>)}
                    </select>
                }
              </>
            )}
            <Label>Novo valor</Label>
            <input value={ufValue} onChange={e => setUfValue(e.target.value)} placeholder="Valor a definir..." style={INPUT} />
          </div>
        )}

        {/* ── ADD/REMOVE TAG ── */}
        {(type === 'add_tag' || type === 'remove_tag') && (
          <div>
            <Label>{type === 'add_tag' ? 'Tag a adicionar' : 'Tag a remover'}</Label>
            {TAGS.length === 0
              ? <div style={{ fontSize: 12, color: '#9ca3af', marginTop: 4 }}>Nenhuma tag cadastrada.</div>
              : <select value={tagId} onChange={e => setTagId(e.target.value)} style={{ ...INPUT, cursor: 'pointer' }}>
                  <option value="">— Selecionar tag —</option>
                  {TAGS.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                </select>
            }
          </div>
        )}

        {/* ── ADD/REMOVE LIST ── */}
        {(type === 'add_list' || type === 'remove_list') && (
          <div>
            <Label>{type === 'add_list' ? 'Lista de destino' : 'Lista a sair'}</Label>
            {LISTAS.length === 0
              ? <div style={{ fontSize: 12, color: '#9ca3af', marginTop: 4 }}>Nenhuma lista cadastrada.</div>
              : <select value={listaId} onChange={e => setListaId(e.target.value)} style={{ ...INPUT, cursor: 'pointer' }}>
                  <option value="">— Selecionar lista —</option>
                  {LISTAS.map(l => <option key={l.id} value={l.id}>{l.name}</option>)}
                </select>
            }
          </div>
        )}

        {/* ── GO_TO ── */}
        {type === 'go_to' && (
          <div>
            <Label>Automação de destino</Label>
            {AUTOMATIONS.filter(a => a.id !== CURRENT_AUTO_ID).length === 0
              ? <div style={{ fontSize: 12, color: '#9ca3af', marginTop: 4 }}>Nenhuma outra automação encontrada.</div>
              : <select value={gotoAutoId} onChange={e => setGotoAutoId(e.target.value)} style={{ ...INPUT, cursor: 'pointer' }}>
                  <option value="">— Selecionar automação —</option>
                  {AUTOMATIONS.filter(a => String(a.id) !== String(CURRENT_AUTO_ID)).map(a => (
                    <option key={a.id} value={a.id}>{a.name}</option>
                  ))}
                </select>
            }
            <div style={{ marginTop: 8, fontSize: 11, color: '#6b7280', lineHeight: 1.5 }}>
              O contato será inserido no início da automação selecionada (se ainda não passou por ela).
            </div>
          </div>
        )}

        {/* ── HUMAN_TRANSFER ── */}
        {type === 'human_transfer' && (
          <div>
            <Label>Mensagem antes da transferência (opcional)</Label>
            <textarea value={htMessage} onChange={e => setHtMessage(e.target.value)} rows={4} placeholder="Ex: Aguarde, vou transferir para um atendente..." style={{ ...INPUT, resize: 'vertical' }} />
            <Label>Tag a adicionar ao contato (opcional)</Label>
            {TAGS.length === 0
              ? <div style={{ fontSize: 12, color: '#9ca3af', marginTop: 4 }}>Nenhuma tag cadastrada.</div>
              : <select value={htTagId} onChange={e => setHtTagId(e.target.value)} style={{ ...INPUT, cursor: 'pointer' }}>
                  <option value="">— Nenhuma —</option>
                  {TAGS.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                </select>
            }
          </div>
        )}
      </div>

      {/* Footer */}
      <div style={{ padding: '12px 16px', borderTop: '1px solid #f3f4f6', display: 'flex', gap: 8 }}>
        <button onClick={apply} style={{ flex: 1, padding: '9px 16px', borderRadius: 8, background: meta.ic || '#4f46e5', color: '#fff', border: 'none', fontSize: 13, fontWeight: 700, cursor: 'pointer' }}>
          Aplicar
        </button>
        <button onClick={onClose} style={{ padding: '9px 14px', borderRadius: 8, background: '#f3f4f6', color: '#374151', border: 'none', fontSize: 13, cursor: 'pointer' }}>
          Cancelar
        </button>
      </div>
    </div>
  );
}

// ── Toast ────────────────────────────────────────────────────────
function Toast({ message, type }) {
  return (
    <div style={{
      position: 'fixed', bottom: 28, left: '50%', transform: 'translateX(-50%)',
      background: type === 'success' ? '#10b981' : '#ef4444', color: '#fff',
      padding: '10px 22px', borderRadius: 10, fontSize: 13, fontWeight: 700,
      boxShadow: '0 4px 20px rgba(0,0,0,0.18)', zIndex: 9999,
      display: 'flex', alignItems: 'center', gap: 8, whiteSpace: 'nowrap',
    }}>
      {type === 'success' ? '✓' : '✗'} {message}
    </div>
  );
}

// ── Default edge options ─────────────────────────────────────────
const DEFAULT_EDGE = {
  type: 'smoothstep',
  animated: false,
  style: { stroke: '#94a3b8', strokeWidth: 2 },
  markerEnd: { type: MarkerType.ArrowClosed, color: '#94a3b8' },
};

// ── Main editor ──────────────────────────────────────────────────
function FlowEditorInner() {
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [selectedNodeId, setSelectedNodeId] = useState(null);
  const [loading, setSaving_Loading] = useState(true);
  const [saving,  setSaving]  = useState(false);
  const [toast,   setToast]   = useState(null);
  const [showTest, setShowTest] = useState(false);
  const wrapperRef = useRef(null);
  const { screenToFlowPosition, fitView } = useReactFlow();

  const setLoading = setSaving_Loading;

  const normNode = n => ({
    id: n.id, type: n.type, position: n.position,
    data: { ...n.data, label: n.data?.label ?? META[n.type]?.label ?? n.type },
  });
  const normEdge = e => ({ ...DEFAULT_EDGE, id: e.id, source: e.source, target: e.target, sourceHandle: e.sourceHandle || 'default', targetHandle: e.targetHandle || 'input' });

  useEffect(() => {
    if (!FLOW_DATA_URL) { setLoading(false); return; }
    fetch(FLOW_DATA_URL, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json())
      .then(({ nodes: ns, edges: es }) => {
        setNodes((ns || []).map(normNode));
        setEdges((es || []).map(normEdge));
        setTimeout(() => fitView({ padding: 0.25 }), 120);
      })
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  const showToast = (msg, type = 'success') => {
    setToast({ message: msg, type });
    setTimeout(() => setToast(null), 2800);
  };

  const saveFlow = useCallback(() => {
    if (!FLOW_UPDATE_URL) return;
    setSaving(true);
    const payload = {
      nodes: nodes.map(n => ({ id: n.id, type: n.type, position: n.position, data: { label: n.data?.label, config: n.data?.config || {} } })),
      edges: edges.map(e => ({ source: e.source, target: e.target, sourceHandle: e.sourceHandle || 'default', targetHandle: e.targetHandle || 'input' })),
    };
    fetch(FLOW_UPDATE_URL, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload),
    })
      .then(r => r.json())
      .then(d => {
        if (d.ok) {
          showToast('Fluxo salvo!', 'success');
          fetch(FLOW_DATA_URL, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(({ nodes: ns, edges: es }) => { setNodes((ns || []).map(normNode)); setEdges((es || []).map(normEdge)); })
            .catch(() => {});
        } else {
          showToast(d.message || 'Erro ao salvar', 'error');
        }
      })
      .catch(() => showToast('Erro de conexão', 'error'))
      .finally(() => setSaving(false));
  }, [nodes, edges]);

  const onConnect = useCallback((params) => {
    setEdges(es => addEdge({ ...DEFAULT_EDGE, ...params, sourceHandle: params.sourceHandle || 'default', targetHandle: params.targetHandle || 'input' }, es));
  }, [setEdges]);

  const onNodeClick   = useCallback((_, node) => setSelectedNodeId(node.id), []);
  const onPaneClick   = useCallback(() => setSelectedNodeId(null), []);

  const addNode = useCallback((type, position) => {
    const id  = `${type}-${Date.now()}`;
    const pos = position || { x: 200 + Math.random() * 200, y: 100 + Math.random() * 200 };
    const defaults = { delay: { minutes: 60 }, send_message: { message_type: 'text' } };
    setNodes(ns => [...ns, { id, type, position: pos, data: { label: META[type]?.label || type, config: defaults[type] || {} } }]);
    setSelectedNodeId(id);
  }, [setNodes]);

  const onDrop = useCallback((e) => {
    e.preventDefault();
    const type = e.dataTransfer.getData('application/rf-type');
    if (!type || !wrapperRef.current) return;
    addNode(type, screenToFlowPosition({ x: e.clientX, y: e.clientY }));
  }, [screenToFlowPosition, addNode]);

  const onDragOver = useCallback((e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; }, []);

  const updateNodeData = useCallback((newData) => {
    setNodes(ns => ns.map(n => n.id === selectedNodeId ? { ...n, data: { ...n.data, ...newData } } : n));
  }, [selectedNodeId, setNodes]);

  const selectedNode = nodes.find(n => n.id === selectedNodeId) || null;

  const handleTestLoading = useCallback((loading) => {
    setNodes(ns => ns.map(n => ({ ...n, data: { ...n.data, testLoading: loading, testResult: loading ? null : n.data.testResult } })));
  }, [setNodes]);

  const handleTestResults = useCallback((details) => {
    const map = {};
    details.forEach(d => { if (d.node_id) map[String(d.node_id)] = d; });
    setNodes(ns => ns.map(n => ({
      ...n,
      data: { ...n.data, testLoading: false, testResult: map[String(n.id)] || null },
    })));
  }, [setNodes]);

  if (loading) {
    return (
      <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#f8fafc', flexDirection: 'column', gap: 12 }}>
        <div style={{ width: 36, height: 36, border: '3px solid #e2e8f0', borderTopColor: '#10b981', borderRadius: '50%', animation: 'spin .8s linear infinite' }} />
        <div style={{ fontSize: 14, color: '#94a3b8' }}>Carregando fluxo…</div>
        <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
      </div>
    );
  }

  return (
    <>
    <style>{`@keyframes scanLine { 0%{background-position:-100% 0} 100%{background-position:200% 0} }`}</style>
    <div style={{ display: 'flex', height: '100%', width: '100%' }}>
        <NodePalette onAddNode={addNode} />
        <div ref={wrapperRef} style={{ flex: 1, height: '100%', position: 'relative' }}>
          <ReactFlow
            nodes={nodes} edges={edges}
            onNodesChange={onNodesChange} onEdgesChange={onEdgesChange}
            onConnect={onConnect} onNodeClick={onNodeClick} onPaneClick={onPaneClick}
            onDrop={onDrop} onDragOver={onDragOver}
            nodeTypes={nodeTypes}
            defaultEdgeOptions={DEFAULT_EDGE}
            deleteKeyCode={['Backspace', 'Delete']}
            connectionLineStyle={{ stroke: '#94a3b8', strokeWidth: 2 }}
            connectionLineType="smoothstep"
            minZoom={0.2} maxZoom={2.5} fitView={false}
          >
            <Background variant="dots" gap={20} size={1} color="#cbd5e1" />
            <Controls style={{ left: 12, bottom: 12 }} showInteractive={false} />
            <MiniMap nodeColor={n => META[n.type]?.ic || '#94a3b8'} style={{ right: 12, bottom: 12, borderRadius: 10 }} pannable zoomable />

            <Panel position="top-right" style={{ margin: '10px 10px 0 0', display: 'flex', gap: 8 }}>
              <button
                onClick={() => { setShowTest(v => !v); setTestResults({}); }}
                style={{ display: 'inline-flex', alignItems: 'center', gap: 6, padding: '7px 14px', borderRadius: 8, background: showTest ? '#ecfdf5' : '#fff', color: showTest ? '#065f46' : '#374151', border: `1px solid ${showTest ? '#6ee7b7' : '#e5e7eb'}`, cursor: 'pointer', fontSize: 12.5, fontWeight: 700, boxShadow: '0 1px 4px rgba(0,0,0,.07)', transition: 'all .15s' }}>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                  <path d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                  <path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Testar
              </button>
              <button onClick={saveFlow} disabled={saving} style={{ display: 'inline-flex', alignItems: 'center', gap: 7, padding: '7px 18px', borderRadius: 8, background: saving ? '#6ee7b7' : '#10b981', color: '#fff', border: 'none', cursor: saving ? 'default' : 'pointer', fontSize: 12.5, fontWeight: 700, boxShadow: '0 2px 8px rgba(16,185,129,.28)', transition: 'background .15s' }}>
                {saving ? '⏳ Salvando…' : '💾 Salvar fluxo'}
              </button>
            </Panel>

            {showTest && (
              <Panel position="top-right" style={{ marginTop: 52, marginRight: 10 }}>
                <TestPanel
                  onClose={() => { setShowTest(false); handleTestResults([]); }}
                  onResults={handleTestResults}
                  onLoading={handleTestLoading}
                />
              </Panel>
            )}

            {nodes.length === 0 && (
              <Panel position="top-center" style={{ marginTop: 80, pointerEvents: 'none' }}>
                <div style={{ textAlign: 'center', color: '#94a3b8', lineHeight: 1.7 }}>
                  <div style={{ fontSize: 44, marginBottom: 8 }}>🗺️</div>
                  <div style={{ fontSize: 15, fontWeight: 700, color: '#64748b' }}>Canvas vazio</div>
                  <div style={{ fontSize: 13 }}>Arraste blocos da barra lateral para começar<br/>ou clique em qualquer bloco para adicioná-lo</div>
                </div>
              </Panel>
            )}
          </ReactFlow>
        </div>

        {selectedNode && (
          <PropertiesPanel node={selectedNode} onUpdate={updateNodeData} onClose={() => setSelectedNodeId(null)} />
        )}
        {toast && <Toast message={toast.message} type={toast.type} />}
      </div>
    </>
  );
}

function App() {
  return (
    <ReactFlowProvider>
      <div style={{ height: '100%', width: '100%' }}>
        <FlowEditorInner />
      </div>
    </ReactFlowProvider>
  );
}

const root = document.getElementById('automation-flow-root');
if (root) {
  import('react-dom/client').then(({ createRoot }) => {
    createRoot(root).render(<App />);
  });
}
