# React Flow 進階模式參考

## 自訂 Edge（AnimatedEdge）

```typescript
import { memo } from 'react';
import { BaseEdge, EdgeLabelRenderer, getBezierPath, type EdgeProps } from '@xyflow/react';

function AnimatedEdge({
  id, sourceX, sourceY, targetX, targetY,
  sourcePosition, targetPosition, label,
}: EdgeProps) {
  const [edgePath, labelX, labelY] = getBezierPath({
    sourceX, sourceY, sourcePosition,
    targetX, targetY, targetPosition,
  });

  return (
    <>
      <BaseEdge id={id} path={edgePath} className="animated-edge" />
      {label && (
        <EdgeLabelRenderer>
          <div
            style={{ transform: `translate(-50%, -50%) translate(${labelX}px,${labelY}px)` }}
            className="edge-label nodrag nopan"
          >
            {label}
          </div>
        </EdgeLabelRenderer>
      )}
    </>
  );
}

export default memo(AnimatedEdge);
```

CSS：
```css
.animated-edge path {
  stroke-dasharray: 6;
  animation: dash 1s linear infinite;
}
@keyframes dash {
  to { stroke-dashoffset: -12; }
}
```

---

## 動態新增節點

```typescript
import { useReactFlow } from '@xyflow/react';

export function useAddNode() {
  const { screenToFlowPosition } = useReactFlow();
  const addNode = useFlowStore((s) => s.addNode);

  const addProcessNode = useCallback((screenPos: { x: number; y: number }) => {
    const position = screenToFlowPosition(screenPos);
    addNode({
      id: crypto.randomUUID(),
      type: 'process',
      position,
      data: { label: '新節點', status: 'idle', config: {} },
    });
  }, [screenToFlowPosition, addNode]);

  return { addProcessNode };
}
```

Store 補充：
```typescript
addNode: (node: AppNode) =>
  set((s) => ({ nodes: [...s.nodes, node] })),
```

---

## Undo / Redo（temporal middleware）

```typescript
import { temporal } from 'zundo';
import { create } from 'zustand';

export const useFlowStore = create(
  temporal<FlowState & FlowActions>(
    (set) => ({ /* ...同前... */ }),
    {
      // 只追蹤 nodes 和 edges 的變化
      partialize: ({ nodes, edges }) => ({ nodes, edges }),
    }
  )
);

// 使用
const { undo, redo, clear } = useFlowStore.temporal.getState();
```

依賴：`npm install zundo`

---

## 序列化與反序列化

```typescript
// 匯出
export function serializeFlow(nodes: AppNode[], edges: AppEdge[]) {
  return JSON.stringify({
    version: '1.0',
    nodes: nodes.map(({ id, type, position, data }) => ({ id, type, position, data })),
    edges: edges.map(({ id, source, target, type, label }) => ({ id, source, target, type, label })),
  });
}

// 匯入
export function deserializeFlow(json: string): { nodes: AppNode[]; edges: AppEdge[] } {
  const parsed = JSON.parse(json);
  // 此處可加入 zod 驗證
  return { nodes: parsed.nodes, edges: parsed.edges };
}
```

---

## 多選節點批次操作

```typescript
import { useStore } from '@xyflow/react';

function BatchToolbar() {
  const selectedNodes = useStore((s) => s.nodes.filter((n) => n.selected));
  const updateNodeData = useFlowStore((s) => s.updateNodeData);

  const setAllIdle = () =>
    selectedNodes.forEach((n) => updateNodeData(n.id, { status: 'idle' }));

  if (selectedNodes.length === 0) return null;

  return (
    <div className="batch-toolbar">
      <span>已選取 {selectedNodes.length} 個節點</span>
      <button onClick={setAllIdle}>重設狀態</button>
    </div>
  );
}
```

---

## 右鍵選單（Context Menu）

```typescript
import { useCallback, useState } from 'react';
import { useReactFlow } from '@xyflow/react';

export function useContextMenu() {
  const [menu, setMenu] = useState<{ x: number; y: number; nodeId: string } | null>(null);
  const { deleteElements } = useReactFlow();

  const onNodeContextMenu = useCallback((event: React.MouseEvent, node: AppNode) => {
    event.preventDefault();
    setMenu({ x: event.clientX, y: event.clientY, nodeId: node.id });
  }, []);

  const closeMenu = useCallback(() => setMenu(null), []);

  const deleteNode = useCallback(() => {
    if (!menu) return;
    deleteElements({ nodes: [{ id: menu.nodeId }] });
    closeMenu();
  }, [menu, deleteElements, closeMenu]);

  return { menu, onNodeContextMenu, closeMenu, deleteNode };
}
```

---

## ELK 自動布局（複雜圖形）

```typescript
import ELK from 'elkjs/lib/elk.bundled';

const elk = new ELK();

export async function applyElkLayout(nodes: AppNode[], edges: AppEdge[]) {
  const graph = {
    id: 'root',
    layoutOptions: {
      'elk.algorithm': 'layered',
      'elk.direction': 'RIGHT',
      'elk.spacing.nodeNode': '40',
    },
    children: nodes.map((n) => ({ id: n.id, width: 180, height: 60 })),
    edges: edges.map((e) => ({ id: e.id, sources: [e.source], targets: [e.target] })),
  };

  const layout = await elk.layout(graph);

  return nodes.map((n) => {
    const elkNode = layout.children?.find((c) => c.id === n.id);
    return elkNode
      ? { ...n, position: { x: elkNode.x ?? 0, y: elkNode.y ?? 0 } }
      : n;
  });
}
```

依賴：`npm install elkjs`

---

## 大型圖形效能清單

- [ ] `nodeTypes` / `edgeTypes` 在模組頂層宣告
- [ ] 所有 Custom Node 包 `memo()`
- [ ] Zustand selector 精確訂閱（不訂閱整個 store）
- [ ] 節點數 > 200 時啟用 `onlyRenderVisibleElements`
- [ ] Handle 盡量使用固定 `id`，避免動態 Handle
- [ ] 邊線動畫改用 CSS（非 JS 驅動）
- [ ] 使用 `React.lazy` 延遲載入設定面板

---

## 與後端同步（樂觀更新模式）

```typescript
const updateNodeData = async (id: string, data: Partial<AppNodeData>) => {
  // 樂觀更新本地
  useFlowStore.getState().updateNodeData(id, data);
  
  try {
    await api.patch(`/nodes/${id}`, data);
  } catch {
    // 回滾
    useFlowStore.getState().updateNodeData(id, originalData);
    toast.error('儲存失敗，已回復');
  }
};
```

---

## 拖放 Sidebar 新增節點（screenToFlowPosition）

```typescript
import { useReactFlow } from '@xyflow/react';
import { useCallback } from 'react';

// Sidebar 的可拖曳元素
function SidebarItem({ type }: { type: string }) {
  return (
    <div
      draggable
      onDragStart={(e) => e.dataTransfer.setData('nodeType', type)}
    >
      {type}
    </div>
  );
}

// Flow 畫布：接收拖放
function FlowCanvas() {
  const { screenToFlowPosition } = useReactFlow();

  const onDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    const type = e.dataTransfer.getData('nodeType');
    const position = screenToFlowPosition({ x: e.clientX, y: e.clientY });

    useFlowStore.getState().addNode({
      id: crypto.randomUUID(),
      type,
      position,
      data: { label: `New ${type}` },
    });
  }, [screenToFlowPosition]);

  return (
    <ReactFlow
      onDrop={onDrop}
      onDragOver={(e) => e.preventDefault()}
      {...flowProps}
    />
  );
}
```

---

## 連線驗證（isValidConnection）

```typescript
import { ReactFlow, type IsValidConnection } from '@xyflow/react';

// 在 ReactFlow 層級設定（效能優於在每個 Handle 上設定）
const isValidConnection: IsValidConnection = useCallback((connection) => {
  const { source, target, sourceHandle, targetHandle } = connection;
  // 不允許自環
  if (source === target) return false;
  // 只允許 output → input 的連線
  return sourceHandle?.startsWith('out') && targetHandle?.startsWith('in');
}, []);

<ReactFlow isValidConnection={isValidConnection} {...props} />
```

---

## useHandleConnections — 動態顯示連線數

```typescript
import { useHandleConnections } from '@xyflow/react';

function InputPortLabel({ handleId }: { handleId: string }) {
  const connections = useHandleConnections({ type: 'target', id: handleId });
  
  return (
    <span className={connections.length > 0 ? 'connected' : 'empty'}>
      {connections.length > 0 ? `${connections.length} 連線` : '未連線'}
    </span>
  );
}
```

---

## useNodesData — 跨節點資料訂閱

```typescript
import { useNodesData } from '@xyflow/react';

// 在 Custom Node 內訂閱另一個節點的 data（只在目標 data 改變時重渲）
function DependentNode({ data }: NodeProps<DependentNodeType>) {
  const sourceData = useNodesData<SourceNodeType>(data.sourceNodeId);
  
  return (
    <div>
      來源節點狀態: {sourceData?.data.status ?? '未連線'}
    </div>
  );
}
```

---

## useNodesInitialized — 等待 Layout 準備就緒

```typescript
import { useNodesInitialized, useReactFlow } from '@xyflow/react';
import { useEffect } from 'react';
import { applyElkLayout } from '../utils/layoutUtils';

function AutoLayoutFlow() {
  const nodesInitialized = useNodesInitialized();
  const { fitView } = useReactFlow();
  const { nodes, edges } = useFlowStore();

  useEffect(() => {
    if (!nodesInitialized || nodes.length === 0) return;

    applyElkLayout(nodes, edges).then((layoutedNodes) => {
      useFlowStore.setState({ nodes: layoutedNodes });
      setTimeout(() => fitView({ duration: 400 }), 0);
    });
  }, [nodesInitialized]); // ← 只在初始化完成時觸發一次
}
```

---

## ViewportPortal — Viewport 座標系渲染

```typescript
import { ViewportPortal } from '@xyflow/react';

// 渲染在 flow 座標 [x, y] 的浮動元素（跟隨 zoom/pan）
function FlowAnnotation({ x, y, text }: { x: number; y: number; text: string }) {
  return (
    <ViewportPortal>
      <div
        style={{
          transform: `translate(${x}px, ${y}px)`,
          position: 'absolute',
          background: 'rgba(255,255,200,0.9)',
          padding: '4px 8px',
          borderRadius: 4,
          pointerEvents: 'none',
        }}
      >
        {text}
      </div>
    </ViewportPortal>
  );
}
```

---

## useKeyPress — 鍵盤快捷鍵

```typescript
import { useKeyPress } from '@xyflow/react';
import { useEffect } from 'react';

function FlowShortcuts() {
  const deletePressed = useKeyPress('Delete');
  const cmdZ = useKeyPress(['Meta+z', 'Control+z']);
  const cmdShiftZ = useKeyPress(['Meta+Shift+z', 'Control+Shift+z']);
  const { undo, redo } = useFlowStore.temporal.getState();

  useEffect(() => {
    if (cmdZ) undo();
  }, [cmdZ]);

  useEffect(() => {
    if (cmdShiftZ) redo();
  }, [cmdShiftZ]);

  // useKeyPress 不依賴 ReactFlowInstance，可在任何地方使用
  return null;
}
```
