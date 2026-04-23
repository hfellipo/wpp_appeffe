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

// ── Config from Laravel backend ─────────────────────────────────
const cfg = window.AUTOMATION_FLOW || {};
const FLOW_DATA_URL   = cfg.flowDataUrl   || '';
const FLOW_UPDATE_URL = cfg.flowUpdateUrl || '';
const CSRF            = cfg.csrfToken     || '';
const LISTAS          = cfg.listas        || [];
const TAGS            = cfg.tags          || [];
const TRIGGER_URL     = cfg.triggerEditUrl || '';

// ── Node visual definitions ──────────────────────────────────────
const META = {
  start:        { label: 'Início',            bg: '#ecfdf5', bd: '#6ee7b7', ic: '#10b981', tx: '#065f46' },
  send_message: { label: 'Enviar mensagem',   bg: '#f0f9ff', bd: '#7dd3fc', ic: '#0ea5e9', tx: '#0c4a6e' },
  delay:        { label: 'Aguardar',          bg: '#fffbeb', bd: '#fcd34d', ic: '#d97706', tx: '#78350f' },
  add_tag:      { label: 'Adicionar tag',     bg: '#f5f3ff', bd: '#c4b5fd', ic: '#7c3aed', tx: '#4c1d95' },
  remove_tag:   { label: 'Remover tag',       bg: '#fef2f2', bd: '#fca5a5', ic: '#dc2626', tx: '#7f1d1d' },
  add_list:     { label: 'Adicionar à lista', bg: '#eef2ff', bd: '#a5b4fc', ic: '#4f46e5', tx: '#1e1b4b' },
  remove_list:  { label: 'Remover da lista',  bg: '#fff1f2', bd: '#fda4af', ic: '#e11d48', tx: '#881337' },
};

// SVG paths (one or two paths per icon)
const ICON_PATHS = {
  start:        [['M13 10V3L4 14h7v7l9-11h-7z']],
  send_message: [['M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z']],
  delay:        [['M12 8v4l3 3'], ['M21 12a9 9 0 11-18 0 9 9 0 0118 0z']],
  add_tag:      [['M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z']],
  remove_tag:   [['M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z'], ['M18 6L6 18']],
  add_list:     [['M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4']],
  remove_list:  [['M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'], ['M10 12l4 4m0-4l-4 4']],
};

const PALETTE = [
  { type: 'start',        desc: 'Entrada da jornada' },
  { type: 'send_message', desc: 'Envia mensagem WhatsApp' },
  { type: 'delay',        desc: 'Pausa antes do próximo passo' },
  { type: 'add_tag',      desc: 'Adiciona etiqueta ao contato' },
  { type: 'remove_tag',   desc: 'Remove etiqueta do contato' },
  { type: 'add_list',     desc: 'Adiciona contato a uma lista' },
  { type: 'remove_list',  desc: 'Remove contato de uma lista' },
];

// ── Icon helper ──────────────────────────────────────────────────
function NodeIcon({ type, size = 16, color = '#fff' }) {
  const groups = ICON_PATHS[type] || [];
  return (
    <svg
      width={size} height={size} viewBox="0 0 24 24"
      fill="none" stroke={color}
      strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"
    >
      {groups.map((paths, gi) =>
        paths.map((d, pi) => <path key={`${gi}-${pi}`} d={d} />)
      )}
    </svg>
  );
}

// ── Node summary (what to show inside the card) ──────────────────
function getNodeSummary(type, config) {
  if (!config) return '';
  switch (type) {
    case 'send_message':
      return config.message
        ? config.message.slice(0, 60) + (config.message.length > 60 ? '…' : '')
        : '';
    case 'delay': {
      const m = Number(config.minutes) || 0;
      if (m >= 1440) return `${Math.floor(m / 1440)} dia(s)`;
      if (m >= 60)   return `${Math.floor(m / 60)} hora(s)`;
      return m ? `${m} minuto(s)` : '';
    }
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

// ── Custom Node Component ────────────────────────────────────────
function AutomationNode({ id, type, data, selected }) {
  const { setNodes, setEdges } = useReactFlow();
  const [hovered, setHovered] = useState(false);
  const meta    = META[type] || { label: type, bg: '#f9fafb', bd: '#d1d5db', ic: '#6b7280', tx: '#111827' };
  const summary = getNodeSummary(type, data.config);

  const onDelete = useCallback((e) => {
    e.stopPropagation();
    setNodes(ns => ns.filter(n => n.id !== id));
    setEdges(es => es.filter(e => e.source !== id && e.target !== id));
  }, [id, setNodes, setEdges]);

  const borderColor = selected ? meta.ic : hovered ? `${meta.ic}99` : meta.bd;
  const shadow = selected
    ? `0 0 0 3px ${meta.ic}30, 0 4px 20px rgba(0,0,0,0.14)`
    : hovered ? '0 4px 14px rgba(0,0,0,0.11)' : '0 2px 8px rgba(0,0,0,0.07)';

  return (
    <div
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
      style={{
        background: meta.bg,
        border: `2px solid ${borderColor}`,
        borderRadius: 14,
        minWidth: 214,
        boxShadow: shadow,
        transition: 'border-color 0.15s, box-shadow 0.15s',
        position: 'relative',
        userSelect: 'none',
      }}
    >
      {/* Delete button */}
      <div
        onClick={onDelete}
        title="Remover nó"
        style={{
          position: 'absolute', top: -11, right: -11,
          width: 22, height: 22, borderRadius: '50%',
          background: '#ef4444', color: '#fff',
          cursor: 'pointer',
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          fontSize: 15, lineHeight: 1, fontWeight: 700,
          boxShadow: '0 2px 6px rgba(239,68,68,0.45)',
          opacity: hovered || selected ? 1 : 0,
          transition: 'opacity 0.15s',
          zIndex: 10,
        }}
      >×</div>

      {/* Input handle (hidden on start node) */}
      {type !== 'start' && (
        <Handle
          type="target" position={Position.Left} id="input"
          style={{ background: meta.ic, width: 10, height: 10, border: '2.5px solid #fff', left: -6 }}
        />
      )}

      <div style={{ padding: '11px 14px', display: 'flex', alignItems: 'flex-start', gap: 10 }}>
        {/* Icon badge */}
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

      {/* Output handle */}
      <Handle
        type="source" position={Position.Right} id="default"
        style={{ background: meta.ic, width: 10, height: 10, border: '2.5px solid #fff', right: -6 }}
      />
    </div>
  );
}

// Register all node types with the same component
const nodeTypes = Object.fromEntries(
  Object.keys(META).map(t => [t, AutomationNode])
);

// ── Node Palette (left sidebar) ──────────────────────────────────
function NodePalette({ onAddNode }) {
  const onDragStart = (e, type) => {
    e.dataTransfer.setData('application/rf-type', type);
    e.dataTransfer.effectAllowed = 'move';
  };

  return (
    <div style={{
      width: 198, flexShrink: 0, background: '#fff',
      borderRight: '1px solid #e9ecef',
      overflowY: 'auto', display: 'flex', flexDirection: 'column',
    }}>
      <div style={{ padding: '14px 14px 8px', borderBottom: '1px solid #f3f4f6' }}>
        <div style={{ fontSize: 10.5, fontWeight: 800, textTransform: 'uppercase', letterSpacing: '0.08em', color: '#94a3b8' }}>
          Blocos
        </div>
        <div style={{ fontSize: 10.5, color: '#c4cdd6', marginTop: 2 }}>
          Arraste ou clique para adicionar
        </div>
      </div>

      <div style={{ padding: '8px 8px', flex: 1 }}>
        {PALETTE.map(({ type, desc }) => {
          const m = META[type];
          return (
            <PaletteItem
              key={type}
              type={type}
              desc={desc}
              meta={m}
              onDragStart={onDragStart}
              onAddNode={onAddNode}
            />
          );
        })}
      </div>
    </div>
  );
}

function PaletteItem({ type, desc, meta, onDragStart, onAddNode }) {
  const [hovered, setHovered] = useState(false);
  return (
    <div
      draggable
      onDragStart={(e) => onDragStart(e, type)}
      onClick={() => onAddNode(type)}
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
      style={{
        display: 'flex', alignItems: 'center', gap: 9,
        padding: '7px 9px', borderRadius: 9, marginBottom: 3,
        cursor: 'grab',
        background: hovered ? meta.bg : 'transparent',
        border: `1px solid ${hovered ? meta.bd : 'transparent'}`,
        transition: 'background 0.12s, border-color 0.12s',
        userSelect: 'none',
      }}
    >
      <div style={{
        width: 28, height: 28, borderRadius: 7, background: meta.ic,
        display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0,
      }}>
        <NodeIcon type={type} size={13} />
      </div>
      <div style={{ minWidth: 0 }}>
        <div style={{ fontSize: 12, fontWeight: 600, color: '#374151', lineHeight: 1.2 }}>
          {meta.label}
        </div>
        <div style={{ fontSize: 10.5, color: '#9ca3af', marginTop: 1.5, lineHeight: 1.2 }}>
          {desc}
        </div>
      </div>
    </div>
  );
}

// ── Properties Panel (right sidebar) ─────────────────────────────
function PropertiesPanel({ node, onUpdate, onClose }) {
  const { type, data } = node;
  const config = data.config || {};
  const meta   = META[type] || {};

  const [message,   setMessage]   = useState(config.message || '');
  const [tagId,     setTagId]     = useState(String(config.tag_id   || ''));
  const [listaId,   setListaId]   = useState(String(config.lista_id || ''));

  // Delay: stored as minutes, displayed as value+unit
  const rawMinutes = Number(config.minutes) || 60;
  const [delayUnit, setDelayUnit] = useState(() => {
    if (rawMinutes >= 1440 && rawMinutes % 1440 === 0) return 'days';
    if (rawMinutes >= 60   && rawMinutes % 60   === 0) return 'hours';
    return 'minutes';
  });
  const [delayValue, setDelayValue] = useState(() => {
    if (rawMinutes >= 1440 && rawMinutes % 1440 === 0) return rawMinutes / 1440;
    if (rawMinutes >= 60   && rawMinutes % 60   === 0) return rawMinutes / 60;
    return rawMinutes;
  });

  const totalMinutes = () => {
    const mult = delayUnit === 'days' ? 1440 : delayUnit === 'hours' ? 60 : 1;
    return Math.max(1, Math.min(10080, Math.round(Number(delayValue) * mult)));
  };

  const apply = () => {
    const next = {};
    if (type === 'send_message') {
      next.message = message;
    } else if (type === 'delay') {
      next.minutes = totalMinutes();
    } else if (type === 'add_tag' || type === 'remove_tag') {
      next.tag_id  = tagId  ? Number(tagId)   : null;
    } else if (type === 'add_list' || type === 'remove_list') {
      next.lista_id = listaId ? Number(listaId) : null;
    }
    onUpdate({ ...data, config: next });
    onClose();
  };

  const inputStyle = {
    width: '100%', borderRadius: 8, border: '1px solid #d1d5db',
    padding: '8px 10px', fontSize: 13, outline: 'none',
    boxSizing: 'border-box', fontFamily: 'inherit', lineHeight: 1.5,
    color: '#111827',
  };

  return (
    <div style={{
      width: 280, flexShrink: 0, background: '#fff',
      borderLeft: '1px solid #e9ecef',
      display: 'flex', flexDirection: 'column', height: '100%',
    }}>
      {/* Header */}
      <div style={{
        padding: '13px 16px', borderBottom: '1px solid #f3f4f6',
        display: 'flex', alignItems: 'center', gap: 10,
      }}>
        <div style={{
          width: 30, height: 30, borderRadius: 8, background: meta.ic || '#6b7280',
          display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0,
        }}>
          <NodeIcon type={type} size={14} />
        </div>
        <div style={{ flex: 1 }}>
          <div style={{ fontSize: 13, fontWeight: 700, color: '#111827' }}>{meta.label || type}</div>
          <div style={{ fontSize: 11, color: '#9ca3af' }}>Configurar nó</div>
        </div>
        <button
          onClick={onClose}
          style={{
            background: 'none', border: 'none', cursor: 'pointer',
            color: '#9ca3af', fontSize: 20, lineHeight: 1, padding: 2,
            borderRadius: 4,
          }}
          title="Fechar"
        >×</button>
      </div>

      {/* Body */}
      <div style={{ padding: 16, flex: 1, overflowY: 'auto' }}>

        {type === 'start' && (
          <div>
            <div style={{
              background: '#f0fdf4', border: '1px solid #bbf7d0',
              borderRadius: 10, padding: 12, marginBottom: 12,
            }}>
              <div style={{ fontSize: 12.5, fontWeight: 600, color: '#065f46', marginBottom: 4 }}>
                Ponto de entrada
              </div>
              <div style={{ fontSize: 12, color: '#047857', lineHeight: 1.5 }}>
                O gatilho que dispara esta jornada (tag adicionada, contato em lista, etc.) é configurado separadamente.
              </div>
            </div>
            {TRIGGER_URL && (
              <a
                href={TRIGGER_URL}
                style={{
                  display: 'inline-flex', alignItems: 'center', gap: 5,
                  padding: '8px 14px', borderRadius: 8,
                  background: '#10b981', color: '#fff',
                  textDecoration: 'none', fontSize: 12.5, fontWeight: 600,
                }}
              >
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                  <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                Configurar gatilho e condições
              </a>
            )}
          </div>
        )}

        {type === 'send_message' && (
          <div>
            <label style={{ display: 'block', fontSize: 12, fontWeight: 600, color: '#374151', marginBottom: 5 }}>
              Mensagem WhatsApp
            </label>
            <textarea
              value={message}
              onChange={e => setMessage(e.target.value)}
              rows={6}
              placeholder="Digite a mensagem..."
              style={{ ...inputStyle, resize: 'vertical' }}
            />
            <div style={{ fontSize: 10.5, color: '#9ca3af', marginTop: 5, lineHeight: 1.5 }}>
              Variáveis disponíveis: {'{'}{'{'}<span style={{color:'#0ea5e9'}}>name</span>{'}'}{'}'},  {'{'}{'{'}<span style={{color:'#0ea5e9'}}>phone</span>{'}'}{'}'}
            </div>
          </div>
        )}

        {type === 'delay' && (
          <div>
            <label style={{ display: 'block', fontSize: 12, fontWeight: 600, color: '#374151', marginBottom: 5 }}>
              Aguardar por
            </label>
            <div style={{ display: 'flex', gap: 8 }}>
              <input
                type="number"
                min={1}
                max={delayUnit === 'days' ? 7 : delayUnit === 'hours' ? 168 : 10080}
                value={delayValue}
                onChange={e => setDelayValue(e.target.value)}
                style={{ ...inputStyle, flex: 1, minWidth: 0 }}
              />
              <select
                value={delayUnit}
                onChange={e => setDelayUnit(e.target.value)}
                style={{ ...inputStyle, width: 'auto', cursor: 'pointer' }}
              >
                <option value="minutes">Minutos</option>
                <option value="hours">Horas</option>
                <option value="days">Dias</option>
              </select>
            </div>
            <div style={{
              fontSize: 11, color: '#9ca3af', marginTop: 6,
              background: '#fffbeb', border: '1px solid #fef3c7',
              borderRadius: 6, padding: '4px 8px',
            }}>
              ≈ {totalMinutes()} minuto(s) no total
            </div>
          </div>
        )}

        {(type === 'add_tag' || type === 'remove_tag') && (
          <div>
            <label style={{ display: 'block', fontSize: 12, fontWeight: 600, color: '#374151', marginBottom: 5 }}>
              {type === 'add_tag' ? 'Tag a adicionar' : 'Tag a remover'}
            </label>
            {TAGS.length === 0 ? (
              <div style={{ fontSize: 12, color: '#9ca3af', padding: '6px 0' }}>
                Nenhuma tag cadastrada.
              </div>
            ) : (
              <select
                value={tagId}
                onChange={e => setTagId(e.target.value)}
                style={{ ...inputStyle, cursor: 'pointer' }}
              >
                <option value="">— Selecionar tag —</option>
                {TAGS.map(t => (
                  <option key={t.id} value={t.id}>{t.name}</option>
                ))}
              </select>
            )}
          </div>
        )}

        {(type === 'add_list' || type === 'remove_list') && (
          <div>
            <label style={{ display: 'block', fontSize: 12, fontWeight: 600, color: '#374151', marginBottom: 5 }}>
              {type === 'add_list' ? 'Lista de destino' : 'Lista a sair'}
            </label>
            {LISTAS.length === 0 ? (
              <div style={{ fontSize: 12, color: '#9ca3af', padding: '6px 0' }}>
                Nenhuma lista cadastrada.
              </div>
            ) : (
              <select
                value={listaId}
                onChange={e => setListaId(e.target.value)}
                style={{ ...inputStyle, cursor: 'pointer' }}
              >
                <option value="">— Selecionar lista —</option>
                {LISTAS.map(l => (
                  <option key={l.id} value={l.id}>{l.name}</option>
                ))}
              </select>
            )}
          </div>
        )}
      </div>

      {/* Footer */}
      {type !== 'start' && (
        <div style={{
          padding: '12px 16px', borderTop: '1px solid #f3f4f6',
          display: 'flex', gap: 8,
        }}>
          <button
            onClick={apply}
            style={{
              flex: 1, padding: '9px 16px', borderRadius: 8,
              background: meta.ic || '#4f46e5', color: '#fff',
              border: 'none', fontSize: 13, fontWeight: 700, cursor: 'pointer',
            }}
          >
            Aplicar
          </button>
          <button
            onClick={onClose}
            style={{
              padding: '9px 14px', borderRadius: 8,
              background: '#f3f4f6', color: '#374151',
              border: 'none', fontSize: 13, cursor: 'pointer',
            }}
          >
            Cancelar
          </button>
        </div>
      )}
    </div>
  );
}

// ── Toast Notification ───────────────────────────────────────────
function Toast({ message, type }) {
  const bg = type === 'success' ? '#10b981' : '#ef4444';
  const icon = type === 'success' ? '✓' : '✗';
  return (
    <div style={{
      position: 'fixed', bottom: 28, left: '50%', transform: 'translateX(-50%)',
      background: bg, color: '#fff',
      padding: '10px 20px', borderRadius: 10,
      fontSize: 13, fontWeight: 700,
      boxShadow: '0 4px 18px rgba(0,0,0,0.18)',
      zIndex: 9999, display: 'flex', alignItems: 'center', gap: 8,
      whiteSpace: 'nowrap',
    }}>
      <span style={{ fontSize: 15 }}>{icon}</span>
      {message}
    </div>
  );
}

// ── Edge default options ─────────────────────────────────────────
const DEFAULT_EDGE = {
  type: 'smoothstep',
  animated: false,
  style: { stroke: '#94a3b8', strokeWidth: 2 },
  markerEnd: { type: MarkerType.ArrowClosed, color: '#94a3b8' },
};

// ── Main editor (must be inside ReactFlowProvider) ───────────────
function FlowEditorInner() {
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [selectedNodeId, setSelectedNodeId] = useState(null);
  const [loading,  setLoading]  = useState(true);
  const [saving,   setSaving]   = useState(false);
  const [toast,    setToast]    = useState(null);
  const wrapperRef = useRef(null);
  const { screenToFlowPosition, fitView } = useReactFlow();

  // Load persisted flow
  useEffect(() => {
    if (!FLOW_DATA_URL) { setLoading(false); return; }
    fetch(FLOW_DATA_URL, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(r => r.json())
      .then(({ nodes: ns, edges: es }) => {
        setNodes((ns || []).map(n => ({
          id: n.id, type: n.type, position: n.position,
          data: { ...n.data, label: n.data?.label ?? META[n.type]?.label ?? n.type },
        })));
        setEdges((es || []).map(e => ({
          ...DEFAULT_EDGE,
          id: e.id, source: e.source, target: e.target,
          sourceHandle: e.sourceHandle || 'default',
          targetHandle: e.targetHandle || 'input',
        })));
        setTimeout(() => fitView({ padding: 0.25 }), 120);
      })
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  const showToast = (message, type = 'success') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 2800);
  };

  // Save flow to backend
  const saveFlow = useCallback(() => {
    if (!FLOW_UPDATE_URL) return;
    setSaving(true);

    const payload = {
      nodes: nodes.map(n => ({
        id: n.id, type: n.type, position: n.position,
        data: { label: n.data?.label, config: n.data?.config || {} },
      })),
      edges: edges.map(e => ({
        source: e.source, target: e.target,
        sourceHandle: e.sourceHandle || 'default',
        targetHandle: e.targetHandle || 'input',
      })),
    };

    fetch(FLOW_UPDATE_URL, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': CSRF,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(payload),
    })
      .then(r => r.json())
      .then(d => {
        if (d.ok) {
          showToast('Fluxo salvo com sucesso!', 'success');
          // Reload to sync persisted IDs
          fetch(FLOW_DATA_URL, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          })
            .then(r => r.json())
            .then(({ nodes: ns, edges: es }) => {
              setNodes((ns || []).map(n => ({
                id: n.id, type: n.type, position: n.position,
                data: { ...n.data, label: n.data?.label ?? META[n.type]?.label ?? n.type },
              })));
              setEdges((es || []).map(e => ({
                ...DEFAULT_EDGE,
                id: e.id, source: e.source, target: e.target,
                sourceHandle: e.sourceHandle || 'default',
                targetHandle: e.targetHandle || 'input',
              })));
            })
            .catch(() => {});
        } else {
          showToast(d.message || 'Erro ao salvar', 'error');
        }
      })
      .catch(() => showToast('Erro de conexão ao salvar', 'error'))
      .finally(() => setSaving(false));
  }, [nodes, edges]);

  const onConnect = useCallback((params) => {
    setEdges(es => addEdge({
      ...DEFAULT_EDGE,
      ...params,
      sourceHandle: params.sourceHandle || 'default',
      targetHandle: params.targetHandle || 'input',
    }, es));
  }, [setEdges]);

  const onNodeClick = useCallback((_, node) => setSelectedNodeId(node.id), []);
  const onPaneClick = useCallback(() => setSelectedNodeId(null), []);

  const addNode = useCallback((type, position) => {
    const id = `${type}-${Date.now()}`;
    const pos = position || {
      x: 200 + Math.random() * 200,
      y: 100 + Math.random() * 200,
    };
    setNodes(ns => [...ns, {
      id, type, position: pos,
      data: {
        label: META[type]?.label || type,
        config: type === 'delay' ? { minutes: 60 } : {},
      },
    }]);
    setSelectedNodeId(id);
  }, [setNodes]);

  const onDrop = useCallback((e) => {
    e.preventDefault();
    const type = e.dataTransfer.getData('application/rf-type');
    if (!type || !wrapperRef.current) return;
    const pos = screenToFlowPosition({ x: e.clientX, y: e.clientY });
    addNode(type, pos);
  }, [screenToFlowPosition, addNode]);

  const onDragOver = useCallback((e) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
  }, []);

  const updateNodeData = useCallback((newData) => {
    setNodes(ns => ns.map(n => n.id === selectedNodeId ? { ...n, data: { ...n.data, ...newData } } : n));
  }, [selectedNodeId, setNodes]);

  const selectedNode = nodes.find(n => n.id === selectedNodeId) || null;

  if (loading) {
    return (
      <div style={{
        flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center',
        background: '#f8fafc', flexDirection: 'column', gap: 12,
      }}>
        <div style={{
          width: 40, height: 40, border: '3px solid #e2e8f0',
          borderTopColor: '#10b981', borderRadius: '50%',
          animation: 'spin 0.8s linear infinite',
        }} />
        <div style={{ fontSize: 14, color: '#94a3b8' }}>Carregando fluxo...</div>
        <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
      </div>
    );
  }

  return (
    <div style={{ display: 'flex', height: '100%', width: '100%' }}>
      {/* Left palette */}
      <NodePalette onAddNode={addNode} />

      {/* Canvas */}
      <div ref={wrapperRef} style={{ flex: 1, height: '100%', position: 'relative' }}>
        <ReactFlow
          nodes={nodes}
          edges={edges}
          onNodesChange={onNodesChange}
          onEdgesChange={onEdgesChange}
          onConnect={onConnect}
          onNodeClick={onNodeClick}
          onPaneClick={onPaneClick}
          onDrop={onDrop}
          onDragOver={onDragOver}
          nodeTypes={nodeTypes}
          defaultEdgeOptions={DEFAULT_EDGE}
          deleteKeyCode={['Backspace', 'Delete']}
          connectionLineStyle={{ stroke: '#94a3b8', strokeWidth: 2 }}
          connectionLineType="smoothstep"
          minZoom={0.2}
          maxZoom={2.5}
          fitView={false}
        >
          <Background variant="dots" gap={20} size={1} color="#cbd5e1" />
          <Controls style={{ left: 12, bottom: 12 }} showInteractive={false} />
          <MiniMap
            nodeColor={n => META[n.type]?.ic || '#94a3b8'}
            style={{ right: 12, bottom: 12, borderRadius: 10 }}
            pannable zoomable
          />

          {/* Floating toolbar */}
          <Panel position="top-right" style={{ margin: '10px 10px 0 0' }}>
            <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
              {TRIGGER_URL && (
                <a
                  href={TRIGGER_URL}
                  style={{
                    display: 'inline-flex', alignItems: 'center', gap: 6,
                    padding: '7px 13px', borderRadius: 8,
                    background: '#fff', color: '#374151',
                    textDecoration: 'none', fontSize: 12.5, fontWeight: 600,
                    border: '1px solid #e5e7eb',
                    boxShadow: '0 1px 4px rgba(0,0,0,0.07)',
                  }}
                >
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2">
                    <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                  </svg>
                  Gatilho
                </a>
              )}
              <button
                onClick={saveFlow}
                disabled={saving}
                style={{
                  display: 'inline-flex', alignItems: 'center', gap: 7,
                  padding: '7px 18px', borderRadius: 8,
                  background: saving ? '#6ee7b7' : '#10b981',
                  color: '#fff', border: 'none',
                  cursor: saving ? 'default' : 'pointer',
                  fontSize: 12.5, fontWeight: 700,
                  boxShadow: '0 2px 8px rgba(16,185,129,0.28)',
                  transition: 'background 0.15s',
                }}
              >
                {saving ? (
                  <>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2"
                      style={{ animation: 'spin 0.8s linear infinite' }}>
                      <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                    </svg>
                    Salvando…
                  </>
                ) : (
                  <>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2">
                      <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/>
                      <polyline points="17 21 17 13 7 13 7 21"/>
                      <polyline points="7 3 7 8 15 8"/>
                    </svg>
                    Salvar fluxo
                  </>
                )}
              </button>
            </div>
          </Panel>

          {/* Empty state hint */}
          {nodes.length === 0 && (
            <Panel position="top-center" style={{ marginTop: 80, pointerEvents: 'none' }}>
              <div style={{ textAlign: 'center', color: '#94a3b8', lineHeight: 1.7 }}>
                <div style={{ fontSize: 44, marginBottom: 8 }}>🗺️</div>
                <div style={{ fontSize: 15, fontWeight: 700, color: '#64748b' }}>Canvas vazio</div>
                <div style={{ fontSize: 13 }}>
                  Arraste blocos da barra lateral para começar
                  <br />ou clique em qualquer bloco para adicioná-lo
                </div>
              </div>
            </Panel>
          )}
        </ReactFlow>
      </div>

      {/* Right properties panel */}
      {selectedNode && (
        <PropertiesPanel
          node={selectedNode}
          onUpdate={updateNodeData}
          onClose={() => setSelectedNodeId(null)}
        />
      )}

      {/* Toast */}
      {toast && <Toast message={toast.message} type={toast.type} />}
    </div>
  );
}

// ── App root (wraps with provider) ───────────────────────────────
function App() {
  return (
    <ReactFlowProvider>
      <div style={{ height: '100%', width: '100%' }}>
        <FlowEditorInner />
      </div>
    </ReactFlowProvider>
  );
}

// ── Mount ────────────────────────────────────────────────────────
const root = document.getElementById('automation-flow-root');
if (root) {
  import('react-dom/client').then(({ createRoot }) => {
    createRoot(root).render(<App />);
  });
}
