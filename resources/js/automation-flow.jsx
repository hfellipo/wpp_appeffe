import React, { useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
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
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';

const config = window.AUTOMATION_FLOW || {};
const flowDataUrl = config.flowDataUrl || '';
const flowUpdateUrl = config.flowUpdateUrl || '';
const csrfToken = config.csrfToken || '';
const listas = config.listas || [];
const tags = config.tags || [];
const nodeTypesLabel = config.nodeTypes || {};

function StartNode({ data }) {
  return (
    <div className="px-4 py-3 rounded-xl border-2 border-emerald-200 bg-emerald-50 shadow-sm min-w-[140px]">
      <Handle type="target" position={Position.Left} className="!w-3 !h-3 !border-2 !bg-white" />
      <div className="flex items-center gap-2">
        <span className="text-emerald-600">▶</span>
        <span className="font-medium text-gray-800">{data.label || 'Início'}</span>
      </div>
      <Handle type="source" position={Position.Right} id="default" className="!w-3 !h-3 !border-2 !bg-white" />
    </div>
  );
}

function MessageNode({ data }) {
  const msg = (data.config && data.config.message) || '';
  return (
    <div className="px-4 py-3 rounded-xl border-2 border-amber-200 bg-amber-50 shadow-sm min-w-[180px] max-w-[220px]">
      <Handle type="target" position={Position.Left} className="!w-3 !h-3 !border-2 !bg-white" />
      <div className="font-medium text-amber-800 text-sm">Mensagem</div>
      <p className="text-xs text-gray-600 mt-1 truncate">{msg || '—'}</p>
      <Handle type="source" position={Position.Right} id="default" className="!w-3 !h-3 !border-2 !bg-white" />
    </div>
  );
}

function DelayNode({ data }) {
  const min = (data.config && data.config.minutes) || 0;
  return (
    <div className="px-4 py-3 rounded-xl border-2 border-blue-200 bg-blue-50 shadow-sm min-w-[140px]">
      <Handle type="target" position={Position.Left} className="!w-3 !h-3 !border-2 !bg-white" />
      <div className="font-medium text-blue-800 text-sm">Aguardar</div>
      <p className="text-xs text-gray-600 mt-1">{min ? `${min} min` : '—'}</p>
      <Handle type="source" position={Position.Right} id="default" className="!w-3 !h-3 !border-2 !bg-white" />
    </div>
  );
}

function AddTagNode({ data }) {
  const tagId = (data.config && data.config.tag_id) || '';
  const tag = tags.find((t) => String(t.id) === String(tagId));
  return (
    <div className="px-4 py-3 rounded-xl border-2 border-purple-200 bg-purple-50 shadow-sm min-w-[140px]">
      <Handle type="target" position={Position.Left} className="!w-3 !h-3 !border-2 !bg-white" />
      <div className="font-medium text-purple-800 text-sm">Adicionar tag</div>
      <p className="text-xs text-gray-600 mt-1">{tag ? tag.name : '—'}</p>
      <Handle type="source" position={Position.Right} id="default" className="!w-3 !h-3 !border-2 !bg-white" />
    </div>
  );
}

function AddListNode({ data }) {
  const listId = (data.config && data.config.lista_id) || '';
  const lista = listas.find((l) => String(l.id) === String(listId));
  return (
    <div className="px-4 py-3 rounded-xl border-2 border-indigo-200 bg-indigo-50 shadow-sm min-w-[140px]">
      <Handle type="target" position={Position.Left} className="!w-3 !h-3 !border-2 !bg-white" />
      <div className="font-medium text-indigo-800 text-sm">Adicionar à lista</div>
      <p className="text-xs text-gray-600 mt-1">{lista ? lista.name : '—'}</p>
      <Handle type="source" position={Position.Right} id="default" className="!w-3 !h-3 !border-2 !bg-white" />
    </div>
  );
}

const nodeTypes = {
  start: StartNode,
  send_message: MessageNode,
  delay: DelayNode,
  add_tag: AddTagNode,
  add_list: AddListNode,
};

function PropsPanel({ node, onUpdate, onClose }) {
  if (!node) return null;
  const { type, data } = node;
  const config = data.config || {};
  const [message, setMessage] = useState(config.message || '');
  const [minutes, setMinutes] = useState(config.minutes ?? 5);
  const [tagId, setTagId] = useState(config.tag_id ? String(config.tag_id) : '');
  const [listaId, setListaId] = useState(config.lista_id ? String(config.lista_id) : '');

  const save = () => {
    const next = { ...config };
    if (type === 'send_message') next.message = message;
    if (type === 'delay') next.minutes = Math.max(1, Math.min(10080, Number(minutes) || 5));
    if (type === 'add_tag') next.tag_id = tagId ? Number(tagId) : null;
    if (type === 'add_list') next.lista_id = listaId ? Number(listaId) : null;
    onUpdate({ ...data, config: next });
    onClose();
  };

  return (
    <div className="p-4">
      {type === 'send_message' && (
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Mensagem</label>
          <textarea
            value={message}
            onChange={(e) => setMessage(e.target.value)}
            rows={4}
            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
            placeholder="Digite a mensagem..."
          />
        </div>
      )}
      {type === 'delay' && (
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Aguardar (minutos)</label>
          <input
            type="number"
            min={1}
            max={10080}
            value={minutes}
            onChange={(e) => setMinutes(e.target.value)}
            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
          />
        </div>
      )}
      {type === 'add_tag' && (
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Tag</label>
          <select
            value={tagId}
            onChange={(e) => setTagId(e.target.value)}
            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
          >
            <option value="">— Selecionar —</option>
            {tags.map((t) => (
              <option key={t.id} value={t.id}>{t.name}</option>
            ))}
          </select>
        </div>
      )}
      {type === 'add_list' && (
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Lista</label>
          <select
            value={listaId}
            onChange={(e) => setListaId(e.target.value)}
            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
          >
            <option value="">— Selecionar —</option>
            {listas.map((l) => (
              <option key={l.id} value={l.id}>{l.name}</option>
            ))}
          </select>
        </div>
      )}
      {(type === 'send_message' || type === 'delay' || type === 'add_tag' || type === 'add_list') && (
        <div className="mt-4 flex gap-2">
          <button
            type="button"
            onClick={save}
            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"
          >
            Aplicar
          </button>
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50"
          >
            Fechar
          </button>
        </div>
      )}
    </div>
  );
}

function FlowEditor() {
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [selectedNode, setSelectedNode] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [saveStatus, setSaveStatus] = useState('');

  const onConnect = useCallback(
    (params) => setEdges((eds) => addEdge({ ...params, sourceHandle: params.sourceHandle || 'default', targetHandle: params.targetHandle || 'input' }, eds)),
    [setEdges]
  );

  const onNodeClick = useCallback((_, node) => {
    setSelectedNode(node);
    const panel = document.getElementById('automation-flow-props');
    if (panel) {
      panel.classList.remove('hidden');
      document.getElementById('flow-props-node-type').textContent = nodeTypesLabel[node.type] || node.type;
    }
  }, []);

  useEffect(() => {
    if (!flowDataUrl) {
      setLoading(false);
      return;
    }
    fetch(flowDataUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then((r) => r.json())
      .then(({ nodes: n, edges: e }) => {
        const flowNodes = (n || []).map((nd) => ({
          id: nd.id,
          type: nd.type,
          position: nd.position,
          data: { ...nd.data, label: nd.data?.label ?? nodeTypesLabel[nd.type] },
        }));
        const flowEdges = (e || []).map((ed) => ({
          id: ed.id,
          source: ed.source,
          target: ed.target,
          sourceHandle: ed.sourceHandle || 'default',
          targetHandle: ed.targetHandle || 'input',
        }));
        setNodes(flowNodes);
        setEdges(flowEdges);
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [flowDataUrl, setNodes, setEdges]);

  const addNode = (type) => {
    const id = `${type}-${Date.now()}`;
    const center = { x: 250 + Math.random() * 100, y: 200 + Math.random() * 80 };
    setNodes((nds) => nds.concat({
      id,
      type,
      position: center,
      data: { label: nodeTypesLabel[type] || type, config: type === 'delay' ? { minutes: 5 } : {} },
    }));
  };

  const updateNodeData = (nodeId, newData) => {
    setNodes((nds) => nds.map((n) => (n.id === nodeId ? { ...n, data: { ...n.data, ...newData } } : n)));
    setSelectedNode((prev) => (prev && prev.id === nodeId ? { ...prev, data: { ...prev.data, ...newData } } : prev));
  };

  const saveFlow = () => {
    if (!flowUpdateUrl) return;
    setSaving(true);
    setSaveStatus('');
    const payload = {
      nodes: nodes.map((n) => ({
        id: n.id,
        type: n.type,
        position: n.position,
        data: { label: n.data?.label, config: n.data?.config || {} },
      })),
      edges: edges.map((e) => ({
        source: e.source,
        target: e.target,
        sourceHandle: e.sourceHandle || 'default',
        targetHandle: e.targetHandle || 'input',
      })),
    };
    fetch(flowUpdateUrl, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(payload),
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.ok) {
          setSaveStatus('Salvo!');
          // Refetch to get persisted IDs
          fetch(flowDataUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then((res) => res.json())
            .then(({ nodes: n, edges: e }) => {
              if (n && n.length) {
                setNodes((n || []).map((nd) => ({
                  id: nd.id,
                  type: nd.type,
                  position: nd.position,
                  data: { ...nd.data, label: nd.data?.label ?? nodeTypesLabel[nd.type] },
                })));
              }
              if (e && e.length) setEdges((e || []).map((ed) => ({
                id: ed.id,
                source: ed.source,
                target: ed.target,
                sourceHandle: ed.sourceHandle || 'default',
                targetHandle: ed.targetHandle || 'input',
              })));
            })
            .catch(() => {});
        } else {
          setSaveStatus(data.message || 'Erro ao salvar');
        }
      })
      .catch(() => setSaveStatus('Erro ao salvar'))
      .finally(() => {
        setSaving(false);
        setTimeout(() => setSaveStatus(''), 2000);
      });
  };

  useEffect(() => {
    const btn = document.getElementById('flow-save-btn');
    const text = document.getElementById('flow-save-text');
    if (!btn || !text) return;
    const handler = () => saveFlow();
    btn.addEventListener('click', handler);
    return () => btn.removeEventListener('click', handler);
  }, [nodes, edges]);

  useEffect(() => {
    const palette = document.getElementById('flow-node-palette');
    if (!palette) return;
    const types = ['start', 'send_message', 'delay', 'add_tag', 'add_list'];
    palette.innerHTML = types
      .map(
        (t) =>
          `<button type="button" data-flow-add="${t}" class="w-full text-left px-3 py-2 rounded-lg border border-gray-200 hover:bg-gray-50 hover:border-gray-300 text-sm font-medium text-gray-700">${nodeTypesLabel[t] || t}</button>`
      )
      .join('');
    const onPaletteClick = (e) => {
      const t = e.target.closest('[data-flow-add]');
      if (t) addNode(t.getAttribute('data-flow-add'));
    };
    palette.addEventListener('click', onPaletteClick);
    return () => palette.removeEventListener('click', onPaletteClick);
  }, []);

  if (loading) {
    return (
      <div className="h-full flex items-center justify-center bg-gray-50">
        <p className="text-gray-500">Carregando fluxo...</p>
      </div>
    );
  }

  const propsPanelEl = typeof document !== 'undefined' ? document.getElementById('automation-flow-props') : null;

  return (
    <>
      <ReactFlow
        nodes={nodes}
        edges={edges}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChange}
        onConnect={onConnect}
        onNodeClick={onNodeClick}
        nodeTypes={nodeTypes}
        fitView
        className="bg-gray-50"
        defaultEdgeOptions={{ type: 'smoothstep' }}
        connectionLineType="smoothstep"
      >
        <Background />
        <Controls />
        <MiniMap />
      </ReactFlow>
      {propsPanelEl && selectedNode &&
        createPortal(
          <PropsPanel
            node={selectedNode}
            onUpdate={(newData) => updateNodeData(selectedNode.id, newData)}
            onClose={() => {
              setSelectedNode(null);
              propsPanelEl.classList.add('hidden');
            }}
          />,
          propsPanelEl
        )}
    </>
  );
}

function App() {
  return (
    <div className="h-full w-full">
      <FlowEditor />
    </div>
  );
}

const root = document.getElementById('automation-flow-root');
if (root) {
  import('react-dom/client').then(({ createRoot }) => {
    createRoot(root).render(
      <React.StrictMode>
        <App />
      </React.StrictMode>
    );
  });
}
