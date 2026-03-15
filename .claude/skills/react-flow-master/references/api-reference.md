# React Flow API Reference（@xyflow/react v12+）

完整 API 快查表，涵蓋官方文件所有 Component、Hook、Utils 與核心 Types。

---

## 目錄

1. [主元件](#主元件)
2. [Components](#components)
3. [Hooks](#hooks)
4. [ReactFlowInstance 方法](#reactflowinstance-方法)
5. [Utils](#utils)
6. [核心 Types](#核心-types)

---

## 主元件

### `<ReactFlow />`

```typescript
import { ReactFlow } from '@xyflow/react';
import '@xyflow/react/dist/style.css';
```

#### 資料 Props

| Prop | Type | Default | 說明 |
|------|------|---------|------|
| `nodes` | `Node[]` | — | 受控節點陣列 |
| `edges` | `Edge[]` | — | 受控邊線陣列 |
| `defaultNodes` | `Node[]` | `[]` | 非受控節點初始值 |
| `defaultEdges` | `Edge[]` | `[]` | 非受控邊線初始值 |
| `nodeTypes` | `NodeTypes` | — | 自訂節點類型映射（**務必在模組頂層宣告**）|
| `edgeTypes` | `EdgeTypes` | — | 自訂邊線類型映射 |
| `defaultEdgeOptions` | `DefaultEdgeOptions` | — | 新建邊線的預設選項 |

#### Viewport Props

| Prop | Type | Default | 說明 |
|------|------|---------|------|
| `fitView` | `boolean` | `false` | 初始化時自動 fit view |
| `fitViewOptions` | `FitViewOptions` | — | `{padding, includeHiddenNodes, minZoom, maxZoom, duration, nodes}` |
| `minZoom` | `number` | `0.5` | 最小縮放比例 |
| `maxZoom` | `number` | `2` | 最大縮放比例 |
| `defaultViewport` | `Viewport` | `{x:0, y:0, zoom:1}` | 初始 viewport |
| `snapToGrid` | `boolean` | `false` | 節點對齊網格 |
| `snapGrid` | `[number, number]` | `[15, 15]` | 網格單位（px）|
| `colorMode` | `'light' \| 'dark' \| 'system'` | `'light'` | 顏色主題 |
| `nodeOrigin` | `[number, number]` | `[0, 0]` | 節點錨點（`[0.5,0.5]` 為中心）|

#### 節點互動 Props

| Prop | Type | Default | 說明 |
|------|------|---------|------|
| `nodesDraggable` | `boolean` | `true` | 全局拖曳開關 |
| `nodesConnectable` | `boolean` | `true` | 全局連線開關 |
| `nodesFocusable` | `boolean` | `true` | 全局 focus 開關 |
| `elementsSelectable` | `boolean` | `true` | 允許選取 |
| `selectNodesOnDrag` | `boolean` | `true` | 拖曳時選取節點 |
| `selectionMode` | `'partial' \| 'full'` | `'full'` | 框選模式 |
| `selectionOnDrag` | `boolean` | `false` | 拖曳時啟動框選（Figma 模式）|
| `panOnDrag` | `boolean \| number[]` | `true` | 拖曳平移（可指定按鍵）|
| `panOnScroll` | `boolean` | `false` | 滾輪平移 |
| `zoomOnScroll` | `boolean` | `true` | 滾輪縮放 |
| `zoomOnPinch` | `boolean` | `true` | 捏合縮放 |
| `zoomOnDoubleClick` | `boolean` | `true` | 雙擊縮放 |
| `preventScrolling` | `boolean` | `true` | 阻止頁面滾動 |
| `autoPanOnNodeDrag` | `boolean` | `true` | 拖曳到邊界時自動平移 |
| `autoPanOnConnect` | `boolean` | `true` | 連線到邊界時自動平移 |
| `reconnectRadius` | `number` | `10` | 重新連線的偵測半徑（px）|
| `onlyRenderVisibleElements` | `boolean` | `false` | 只渲染可見元素（效能優化）|
| `deleteKeyCode` | `KeyCode` | `'Backspace'` | 刪除鍵 |
| `selectionKeyCode` | `KeyCode` | `'Shift'` | 框選鍵 |
| `multiSelectionKeyCode` | `KeyCode` | `'Meta'` | 多選鍵 |
| `panActivationKeyCode` | `KeyCode` | `'Space'` | 平移啟動鍵 |
| `zoomActivationKeyCode` | `KeyCode` | `'Meta'` | 縮放啟動鍵 |
| `disableKeyboardA11y` | `boolean` | `false` | 停用鍵盤無障礙 |
| `connectionMode` | `'strict' \| 'loose'` | `'strict'` | 連線模式 |
| `isValidConnection` | `IsValidConnection` | — | 全局連線驗證（效能最佳位置）|

#### 邊線 Props

| Prop | Type | Default | 說明 |
|------|------|---------|------|
| `connectionLineType` | `ConnectionLineType` | `'bezier'` | 連線預覽線型 |
| `connectionLineStyle` | `CSSProperties` | — | 連線預覽線樣式 |
| `connectionLineComponent` | `ConnectionLineComponent` | — | 自訂連線預覽元件 |
| `edgesReconnectable` | `boolean` | `true` | 允許重新連線邊線 |
| `edgesFocusable` | `boolean` | `true` | 邊線可 focus |
| `edgesUpdatable` | `boolean` | `true` | 邊線可更新 |
| `elevateEdgesOnSelect` | `boolean` | `false` | 選取時提升邊線層級 |

#### 事件處理 Props

| Prop | Signature | 說明 |
|------|-----------|------|
| `onNodesChange` | `(changes: NodeChange[]) => void` | 節點變更（拖曳/選取/刪除） |
| `onEdgesChange` | `(changes: EdgeChange[]) => void` | 邊線變更 |
| `onConnect` | `(connection: Connection) => void` | 新建連線 |
| `onConnectStart` | `OnConnectStart` | 開始拖拉連線 |
| `onConnectEnd` | `OnConnectEnd` | 結束拖拉連線 |
| `onNodeClick` | `NodeMouseHandler` | 節點點擊 |
| `onNodeDoubleClick` | `NodeMouseHandler` | 節點雙擊 |
| `onNodeDragStart` | `NodeDragHandler` | 節點拖曳開始 |
| `onNodeDrag` | `NodeDragHandler` | 節點拖曳中 |
| `onNodeDragStop` | `NodeDragHandler` | 節點拖曳結束 |
| `onNodeMouseEnter` | `NodeMouseHandler` | 節點 hover 進入 |
| `onNodeMouseLeave` | `NodeMouseHandler` | 節點 hover 離開 |
| `onNodeContextMenu` | `NodeMouseHandler` | 節點右鍵 |
| `onEdgeClick` | `EdgeMouseHandler` | 邊線點擊 |
| `onEdgeDoubleClick` | `EdgeMouseHandler` | 邊線雙擊 |
| `onEdgeContextMenu` | `EdgeMouseHandler` | 邊線右鍵 |
| `onEdgeMouseEnter` | `EdgeMouseHandler` | 邊線 hover |
| `onReconnect` | `OnReconnect` | 邊線重新連線 |
| `onReconnectStart` | — | 開始重新連線 |
| `onReconnectEnd` | — | 結束重新連線 |
| `onSelectionChange` | `OnSelectionChangeFunc` | 選取內容變更 |
| `onSelectionDragStart` | `SelectionDragHandler` | 框選拖曳開始 |
| `onSelectionDragStop` | `SelectionDragHandler` | 框選拖曳結束 |
| `onSelectionContextMenu` | — | 框選右鍵 |
| `onPaneClick` | `(event: MouseEvent) => void` | 畫布點擊 |
| `onPaneDoubleClick` | `(event: MouseEvent) => void` | 畫布雙擊 |
| `onPaneContextMenu` | `(event: MouseEvent) => void` | 畫布右鍵 |
| `onPaneScroll` | — | 畫布滾動 |
| `onMove` | `OnMove` | viewport 移動中 |
| `onMoveStart` | `OnMove` | viewport 移動開始 |
| `onMoveEnd` | `OnMove` | viewport 移動結束 |
| `onInit` | `OnInit` | ReactFlow 初始化完成，回傳 ReactFlowInstance |
| `onBeforeDelete` | `OnBeforeDelete` | 刪除前的非同步鉤子，可 return false 阻止 |
| `onDelete` | `OnDelete` | 刪除後的鉤子（節點+邊線）|
| `onNodesDelete` | `OnNodesDelete` | 節點被刪除後 |
| `onEdgesDelete` | `OnEdgesDelete` | 邊線被刪除後 |
| `onError` | `OnError` | ReactFlow 錯誤 |

#### 其他 Props

| Prop | Type | Default | 說明 |
|------|------|---------|------|
| `id` | `string` | — | 為多個 ReactFlow 實例指定唯一 ID |
| `style` | `CSSProperties` | — | 容器樣式 |
| `className` | `string` | — | 容器 class |
| `proOptions` | `ProOptions` | — | Pro 版選項（如隱藏 attribution） |
| `nodrag` | `string` | `'nodrag'` | 防止拖曳的 CSS class 名稱 |
| `nopan` | `string` | `'nopan'` | 防止平移的 CSS class 名稱 |
| `nowheel` | `string` | `'nowheel'` | 防止滾輪的 CSS class 名稱 |

---

### `<ReactFlowProvider />`

```typescript
import { ReactFlowProvider } from '@xyflow/react';

// 當 useReactFlow() 需在 <ReactFlow> 同層或父層元件中使用時
<ReactFlowProvider>
  <YourFlowComponent />
</ReactFlowProvider>
```

---

## Components

### `<Handle />`

```typescript
import { Handle, Position } from '@xyflow/react';

<Handle type="source" position={Position.Right} id="output" />
```

| Prop | Type | Default | 說明 |
|------|------|---------|------|
| `type` | `'source' \| 'target'` | — | (**必填**) 連線方向 |
| `position` | `Position` | — | (**必填**) 位置（Left/Top/Right/Bottom）|
| `id` | `string` | — | Handle ID（同節點多 Handle 時必填）|
| `isConnectable` | `boolean` | `true` | 是否可連線 |
| `isConnectableStart` | `boolean` | `true` | 是否可從此 Handle 開始連線 |
| `isConnectableEnd` | `boolean` | `true` | 是否可連線到此 Handle |
| `isValidConnection` | `IsValidConnection` | — | 驗證連線（效能考量：建議放 ReactFlow 層級）|
| `onConnect` | `OnConnect` | — | 連線成功時的 callback |

> ⚠️ 效能：`isValidConnection` 建議在 `<ReactFlow>` 層級設定，而非每個 Handle

---

### `<Background />`

```typescript
import { Background, BackgroundVariant } from '@xyflow/react';

<Background variant={BackgroundVariant.Dots} gap={20} size={2} />
// 支援多層：每層需 unique id
<Background id="1" variant={BackgroundVariant.Lines} />
<Background id="2" variant={BackgroundVariant.Dots} />
```

| Prop | Type | Default | 說明 |
|------|------|---------|------|
| `id` | `string` | — | 多層背景時需唯一 ID |
| `variant` | `'lines' \| 'dots' \| 'cross'` | `'dots'` | 背景樣式 |
| `gap` | `number \| [number,number]` | `20` | 網格間距（px）|
| `size` | `number` | `1` | 點/線的大小 |
| `offset` | `number` | `2` | cross 變體的偏移量 |
| `lineWidth` | `number` | `1` | 線寬 |
| `color` | `string` | — | 前景色 |
| `bgColor` | `string` | — | 背景色 |
| `style` | `CSSProperties` | — | 容器樣式 |

---

### `<Controls />`

```typescript
import { Controls, ControlButton } from '@xyflow/react';

<Controls showZoom showFitView showInteractive position="bottom-left">
  <ControlButton onClick={customAction}>⚡</ControlButton>
</Controls>
```

| Prop | Type | Default | 說明 |
|------|------|---------|------|
| `showZoom` | `boolean` | `true` | 顯示縮放按鈕 |
| `showFitView` | `boolean` | `true` | 顯示 fit view 按鈕 |
| `showInteractive` | `boolean` | `true` | 顯示鎖定互動按鈕 |
| `fitViewOptions` | `FitViewOptions` | — | fit view 選項 |
| `position` | `PanelPosition` | `'bottom-left'` | 控制列位置 |
| `orientation` | `'horizontal' \| 'vertical'` | `'vertical'` | 排列方向 |
| `onZoomIn` | `() => void` | — | zoom in callback |
| `onZoomOut` | `() => void` | — | zoom out callback |
| `onFitView` | `() => void` | — | fit view callback |
| `onInteractiveChange` | `(isInteractive: boolean) => void` | — | 切換互動狀態 |
| `children` | `ReactNode` | — | 自訂按鈕（使用 `<ControlButton>`）|

---

### `<MiniMap />`

```typescript
import { MiniMap } from '@xyflow/react';

<MiniMap
  nodeColor={(node) => node.type === 'process' ? '#00f' : '#ccc'}
  pannable
  zoomable
/>
```

| Prop | Type | Default | 說明 |
|------|------|---------|------|
| `position` | `PanelPosition` | `'bottom-right'` | 小地圖位置 |
| `nodeColor` | `string \| ((node: Node) => string)` | — | 節點顏色 |
| `nodeStrokeColor` | `string \| ((node: Node) => string)` | — | 節點描邊 |
| `nodeClassName` | `string \| ((node: Node) => string)` | — | 節點 CSS class |
| `nodeBorderRadius` | `number` | `5` | 節點圓角 |
| `nodeStrokeWidth` | `number` | — | 節點描邊寬度 |
| `nodeComponent` | `ComponentType<MiniMapNodeProps>` | — | 自訂小地圖節點 |
| `bgColor` | `string` | — | 背景色 |
| `maskColor` | `string` | — | 遮罩色 |
| `pannable` | `boolean` | `false` | 允許拖曳 mini map |
| `zoomable` | `boolean` | `false` | 允許縮放 mini map |
| `inversePan` | `boolean` | `false` | 反轉平移方向 |
| `zoomStep` | `number` | `10` | 每次縮放步進 |
| `onClick` | `(event, position) => void` | — | 點擊 mini map 回調 |
| `ariaLabel` | `string \| null` | — | aria 標籤 |

---

### `<Panel />`

```typescript
import { Panel } from '@xyflow/react';

<Panel position="top-right">
  <button onClick={onLayout}>Auto Layout</button>
</Panel>
```

| Prop | Type | Default | 說明 |
|------|------|---------|------|
| `position` | `PanelPosition` | — | (**必填**) 面板位置 |
| `children` | `ReactNode` | — | 面板內容 |
| `style` | `CSSProperties` | — | 樣式 |
| `className` | `string` | — | CSS class |

`PanelPosition` 可選值：`'top-left' | 'top-center' | 'top-right' | 'bottom-left' | 'bottom-center' | 'bottom-right'`

---

### `<NodeToolbar />`

```typescript
import { NodeToolbar, Position } from '@xyflow/react';

// 在 Custom Node 內部使用
function CustomNode({ id }) {
  return (
    <>
      <NodeToolbar isVisible position={Position.Top}>
        <button>Delete</button>
      </NodeToolbar>
      <div>Node Content</div>
    </>
  );
}
```

| Prop | Type | Default | 說明 |
|------|------|---------|------|
| `nodeId` | `string \| string[]` | — | 指定節點（預設自動取得父節點 ID）|
| `isVisible` | `boolean` | — | 強制顯示/隱藏（預設跟隨選取狀態）|
| `position` | `Position` | `Position.Top` | 工具列位置 |
| `offset` | `number` | `10` | 與節點的距離（px）|
| `align` | `'start' \| 'center' \| 'end'` | `'center'` | 對齊方式 |

---

### `<NodeResizer />`

```typescript
import { NodeResizer } from '@xyflow/react';

function ResizableNode({ data }) {
  return (
    <>
      <NodeResizer minWidth={100} minHeight={30} />
      <div>{data.label}</div>
    </>
  );
}
```

| Prop | Type | Default | 說明 |
|------|------|---------|------|
| `nodeId` | `string` | — | 目標節點 ID（預設自動取得）|
| `isVisible` | `boolean` | — | 強制顯示/隱藏 |
| `minWidth` | `number` | `10` | 最小寬度（px）|
| `minHeight` | `number` | `10` | 最小高度（px）|
| `maxWidth` | `number` | `MAX_VALUE` | 最大寬度 |
| `maxHeight` | `number` | `MAX_VALUE` | 最大高度 |
| `keepAspectRatio` | `boolean` | `false` | 保持比例 |
| `onResizeStart` | `OnResizeStart` | — | resize 開始 |
| `onResize` | `OnResize` | — | resize 中 |
| `onResizeEnd` | `OnResizeEnd` | — | resize 結束 |

### `<NodeResizeControl />`（自訂 UI）

```typescript
import { NodeResizeControl } from '@xyflow/react';
import { GripIcon } from './icons';

<NodeResizeControl variant="line" position="right">
  <GripIcon />
</NodeResizeControl>
```

額外 Props：`color`, `shouldResize`, `autoScale`, `position`, `variant` (`'handle' | 'line'`), `resizeDirection` (`'horizontal' | 'vertical'`)

---

### `<BaseEdge />`

在自訂 Edge 元件中使用，渲染 SVG 路徑：

```typescript
import { BaseEdge, getBezierPath, type EdgeProps } from '@xyflow/react';

function CustomEdge({ id, sourceX, sourceY, targetX, targetY, sourcePosition, targetPosition }: EdgeProps) {
  const [edgePath] = getBezierPath({ sourceX, sourceY, sourcePosition, targetX, targetY, targetPosition });
  return <BaseEdge id={id} path={edgePath} />;
}
```

| Prop | Type | Default | 說明 |
|------|------|---------|------|
| `path` | `string` | — | (**必填**) SVG path 字串 |
| `id` | `string` | — | 邊線 ID |
| `markerStart` | `string` | — | SVG marker ID（起始箭頭）|
| `markerEnd` | `string` | — | SVG marker ID（結尾箭頭）|
| `label` | `ReactNode` | — | 標籤文字 |
| `labelX` | `number` | — | 標籤 X 位置 |
| `labelY` | `number` | — | 標籤 Y 位置 |
| `labelStyle` | `CSSProperties` | — | 標籤樣式 |
| `labelShowBg` | `boolean` | — | 顯示標籤背景 |
| `interactionWidth` | `number` | `20` | 互動偵測寬度（px）|

---

### `<EdgeLabelRenderer />`

在自訂 Edge 中，用來渲染 HTML（非 SVG）的標籤，會跳出 SVG 容器：

```typescript
import { EdgeLabelRenderer } from '@xyflow/react';

// 必須設定 pointerEvents 和 nopan class 才能接收互動
<EdgeLabelRenderer>
  <div
    style={{
      transform: `translate(-50%, -50%) translate(${labelX}px, ${labelY}px)`,
      position: 'absolute',
      pointerEvents: 'all', // ⚠️ 必需：預設無 pointer events
    }}
    className="nodrag nopan" // ⚠️ 必需：防止觸發畫布拖曳
  >
    <button onClick={onEdgeDelete}>×</button>
  </div>
</EdgeLabelRenderer>
```

> ⚠️ EdgeLabelRenderer 中的元素預設 **沒有 pointer events**，需明確設定 `pointerEvents: 'all'`，並加上 `nopan` class

---

### `<EdgeText />`

在自訂 Edge 中顯示文字標籤的輔助元件：

```typescript
import { EdgeText } from '@xyflow/react';

<EdgeText x={labelX} y={labelY} label="文字" labelShowBg labelBgStyle={{ fill: 'white' }} />
```

| Prop | Type | 說明 |
|------|------|------|
| `x` | `number` | (**必填**) SVG x 座標 |
| `y` | `number` | (**必填**) SVG y 座標 |
| `label` | `ReactNode` | 標籤內容 |
| `labelStyle` | `CSSProperties` | 文字樣式 |
| `labelShowBg` | `boolean` | 顯示背景矩形 |
| `labelBgStyle` | `CSSProperties` | 背景樣式 |
| `labelBgPadding` | `[number, number]` | 背景內距 |
| `labelBgBorderRadius` | `number` | 背景圓角 |

---

### `<ViewportPortal />`

將元素渲染在 viewport 座標系中（跟隨 zoom/pan）：

```typescript
import { ViewportPortal } from '@xyflow/react';

<ViewportPortal>
  <div style={{ transform: 'translate(100px, 100px)', position: 'absolute' }}>
    {/* 位於 flow 座標 [100, 100] 的元素，會隨 zoom/pan 移動 */}
  </div>
</ViewportPortal>
```

---

## Hooks

### 資料訂閱 Hooks

```typescript
// useReactFlow — 不觸發 re-render（按需查詢）
const { getNodes, setNodes, fitView, screenToFlowPosition } = useReactFlow();

// useNodes / useEdges — 任何變更都會觸發 re-render
const nodes = useNodes<MyNodeType>();
const edges = useEdges<MyEdgeType>();

// useNodesData — 訂閱特定節點的 data（精確訂閱）
const nodeData = useNodesData('nodeId-1');
const nodesData = useNodesData<NodesType>(['id-1', 'id-2']); // → Pick<Node, 'id'|'type'|'data'>[]

// useViewport — 訂閱 viewport {x, y, zoom}
const { x, y, zoom } = useViewport();

// useStore — 訂閱 Zustand store 切片（最精確，效能最佳）
const nodeCount = useStore((s) => s.nodes.length);
const selectedNodes = useStore(
  (s) => s.nodes.filter((n) => n.selected),
  (a, b) => a.length === b.length  // 等式函式避免不必要重渲
);
```

### 狀態管理 Hooks

```typescript
// useNodesState — 完整受控狀態（含 onNodesChange）
const [nodes, setNodes, onNodesChange] = useNodesState<MyNodeType>(initialNodes);

// useEdgesState
const [edges, setEdges, onEdgesChange] = useEdgesState<MyEdgeType>(initialEdges);
```

### 連線 Hooks

```typescript
// useConnection — 取得連線拖曳中的狀態
const connection = useConnection();
// → ConnectionState: { inProgress, isValid, from, to, fromHandle, toHandle, ... }

// 自訂 selector
const isConnecting = useConnection((c) => c.inProgress);

// useHandleConnections — 特定 Handle 上的連線列表
const connections = useHandleConnections({ type: 'target', id: 'my-handle' });
// → Connection[]

// useNodeConnections — 特定 Node 上的連線列表
const connections = useNodeConnections({ handleType: 'target', handleId: 'my-handle' });
```

### 節點 Hooks

```typescript
// useNodeId — 在 Custom Node 內取得自身 ID
const nodeId = useNodeId(); // → string | null

// useNodesInitialized — 所有節點已測量並有 width/height
const isReady = useNodesInitialized({ includeHiddenNodes: false }); // → boolean
// 使用場景：等待節點尺寸確定後再執行 auto-layout

// useUpdateNodeInternals — 動態新增/移除 Handle 後通知 RF 更新
const updateNodeInternals = useUpdateNodeInternals();
updateNodeInternals('node-id');        // 單個節點
updateNodeInternals(['id-1', 'id-2']); // 多個節點
```

### 事件 Hooks

```typescript
// useOnViewportChange — 訂閱 viewport 移動事件
useOnViewportChange({
  onStart: (viewport) => console.log('start', viewport),
  onChange: (viewport) => console.log('change', viewport),
  onEnd: (viewport) => console.log('end', viewport),
});

// useOnSelectionChange — 訂閱選取變更（callback 必須 memoize！）
const onChange = useCallback(({ nodes, edges }) => {
  console.log('selected nodes:', nodes);
}, []);
useOnSelectionChange({ onChange });

// useKeyPress — 偵測按鍵（不依賴 ReactFlowInstance，可全局使用）
const spacePressed = useKeyPress('Space');
const cmdS = useKeyPress(['Meta+s', 'Strg+s']);
// 選項：target (DOM元素), actInsideInputWithModifier, preventDefault
```

---

## ReactFlowInstance 方法

透過 `useReactFlow()` 取得，**不觸發 re-render**。

### 節點操作

```typescript
const rf = useReactFlow();

rf.getNodes()                    // Node[] — 取得所有節點
rf.setNodes(nodes | updater)     // 設定節點（支援 updater fn）
rf.addNodes(node | nodes)        // 新增節點
rf.getNode(id)                   // Node | undefined
rf.getInternalNode(id)           // InternalNode | undefined（含測量資訊）
rf.updateNode(id, nodeOrUpdater, { replace?: boolean })  // 更新節點
rf.updateNodeData(id, dataOrUpdater, { replace?: boolean }) // 只更新 data
```

### 邊線操作

```typescript
rf.getEdges()                    // Edge[]
rf.setEdges(edges | updater)     // 設定邊線
rf.addEdges(edge | edges)        // 新增邊線
rf.getEdge(id)                   // Edge | undefined
rf.updateEdge(id, edgeOrUpdater, { replace?: boolean })
rf.updateEdgeData(id, dataOrUpdater, { replace?: boolean })
```

### 刪除操作

```typescript
rf.deleteElements({ nodes?: {id}[], edges?: {id}[] })
// → Promise<{ deletedNodes: Node[], deletedEdges: Edge[] }>
// ⚠️ 也會刪除連接被刪節點的邊線
```

### 序列化

```typescript
rf.toObject()
// → { nodes: Node[], edges: Edge[], viewport: Viewport }
```

### 邊界與連線查詢

```typescript
rf.getNodesBounds(nodes | nodeIds)
// → Rect: { x, y, width, height }

rf.getHandleConnections({ type, nodeId, id? })
// → HandleConnection[]

rf.getNodeConnections({ nodeId, type?, handleId? })
// → NodeConnection[]
```

### 交集查詢

```typescript
rf.getIntersectingNodes(nodeOrRect, partially?, nodes?)
// → Node[] — 與給定節點/矩形相交的節點

rf.isNodeIntersecting(nodeOrRect, area, partially?)
// → boolean
```

### Viewport 控制

```typescript
// 縮放
await rf.zoomIn({ duration?: number })
await rf.zoomOut({ duration?: number })
await rf.zoomTo(1.5, { duration: 300 })
const zoom = rf.getZoom()

// Viewport
await rf.setViewport({ x, y, zoom }, { duration? })
const vp = rf.getViewport() // → { x, y, zoom }

// 定位
await rf.setCenter(x, y, { zoom?, duration? })
await rf.fitBounds(rect, { padding?, duration? })
await rf.fitView({ padding?, nodes?, duration?, minZoom?, maxZoom? })

// 座標轉換
const flowPos = rf.screenToFlowPosition({ x: e.clientX, y: e.clientY })
const screenPos = rf.flowToScreenPosition({ x: 100, y: 200 })

// 是否初始化
const ready = rf.viewportInitialized // boolean
```

---

## Utils

### 核心工具函式

```typescript
import {
  addEdge, applyNodeChanges, applyEdgeChanges,
  getBezierPath, getSmoothStepPath, getSimpleBezierPath,
  getConnectedEdges, getIncomers, getOutgoers, getNodesBounds
} from '@xyflow/react';

// onConnect 標準用法
const onConnect = useCallback(
  (connection: Connection) =>
    setEdges((eds) => addEdge(connection, eds)),
  [setEdges]
);

// 受控節點/邊線（不使用 useNodesState 時）
const onNodesChange = useCallback(
  (changes: NodeChange[]) =>
    setNodes((nds) => applyNodeChanges(changes, nds)),
  []
);
const onEdgesChange = useCallback(
  (changes: EdgeChange[]) =>
    setEdges((eds) => applyEdgeChanges(changes, eds)),
  []
);
```

### Edge Path 工具函式

所有路徑工具回傳 `[path, labelX, labelY, offsetX, offsetY]`：

```typescript
// 貝茲曲線（預設 Edge 類型）
const [path, labelX, labelY] = getBezierPath({
  sourceX, sourceY, sourcePosition: Position.Right,
  targetX, targetY, targetPosition: Position.Left,
  curvature: 0.25,  // 預設 0.25
});

// 階梯線（含圓角）
const [path, labelX, labelY] = getSmoothStepPath({
  sourceX, sourceY, sourcePosition,
  targetX, targetY, targetPosition,
  borderRadius: 5,   // 預設 5，設為 0 = 直角階梯
  offset: 20,        // 預設 20
  stepPosition: 0.5, // 0=靠近來源, 1=靠近目標
});

// 簡單貝茲曲線（無控制點調整）
const [path, labelX, labelY] = getSimpleBezierPath({
  sourceX, sourceY, sourcePosition,
  targetX, targetY, targetPosition,
});
```

### 圖形遍歷工具

```typescript
// 取得與指定節點相連的邊線
const connected = getConnectedEdges(nodes, edges);

// 取得連入節點（以 node 為 target 的 source 節點）
const incomers = getIncomers(node, nodes, edges);

// 取得連出節點（以 node 為 source 的 target 節點）
const outgoers = getOutgoers(node, nodes, edges);

// 計算所有節點的邊界矩形
const bounds = getNodesBounds(nodes, { nodeOrigin: [0, 0] });
// → Rect: { x, y, width, height }
// 搭配 fitBounds 實現 fit to selection
```

---

## 核心 Types

### Node

```typescript
type Node<TData = Record<string, unknown>, TType extends string = string> = {
  id: string;                          // 唯一 ID
  type?: TType;                        // 對應 nodeTypes 的 key
  position: { x: number; y: number }; // 節點位置（左上角）
  data: TData;                         // 自訂資料

  // 尺寸（由 ReactFlow 測量）
  width?: number;
  height?: number;
  measured?: { width: number; height: number };

  // 互動控制
  draggable?: boolean;
  selectable?: boolean;
  connectable?: boolean;
  deletable?: boolean;
  focusable?: boolean;
  selected?: boolean;
  dragging?: boolean;
  hidden?: boolean;

  // 外觀
  style?: CSSProperties;
  className?: string;
  zIndex?: number;

  // Handle 方向（僅內建 default/source/target 節點類型）
  sourcePosition?: Position;
  targetPosition?: Position;

  // 進階
  parentId?: string;        // 子流程：父節點 ID
  extent?: 'parent' | CoordinateExtent;  // 限制拖曳範圍
  expandParent?: boolean;   // 超出父節點時擴展父節點
  dragHandle?: string;      // CSS class 作為拖曳把手
  origin?: NodeOrigin;      // 覆蓋 nodeOrigin
  ariaLabel?: string;
};
```

### Edge

```typescript
type Edge<TData = Record<string, unknown>, TType extends string = string> = {
  id: string;
  source: string;             // 來源節點 ID
  target: string;             // 目標節點 ID
  sourceHandle?: string | null;  // 來源 Handle ID
  targetHandle?: string | null;  // 目標 Handle ID
  type?: TType;               // 對應 edgeTypes 的 key（預設: 'default' = bezier）
  data?: TData;

  // 互動
  animated?: boolean;
  hidden?: boolean;
  deletable?: boolean;
  selectable?: boolean;
  focusable?: boolean;
  reconnectable?: boolean | 'source' | 'target';
  interactionWidth?: number;  // 互動偵測寬度（px），預設 20

  // 標籤
  label?: ReactNode;
  labelStyle?: CSSProperties;
  labelShowBg?: boolean;
  labelBgStyle?: CSSProperties;
  labelBgPadding?: [number, number];
  labelBgBorderRadius?: number;

  // 箭頭
  markerStart?: EdgeMarker;
  markerEnd?: EdgeMarker;

  // 外觀
  style?: CSSProperties;
  className?: string;
  zIndex?: number;
  pathOptions?: object;
};

// 內建 type 值：'default'(bezier), 'step', 'smoothstep', 'straight', 'simplebezier'
```

### NodeProps（Custom Node 接收的 props）

```typescript
type NodeProps<TNode extends Node = Node> = {
  id: string;
  data: TNode['data'];
  type?: string;
  width?: number;
  height?: number;
  selected?: boolean;
  dragging?: boolean;
  selectable?: boolean;
  deletable?: boolean;
  draggable?: boolean;
  isConnectable?: boolean;
  positionAbsoluteX: number;
  positionAbsoluteY: number;
  sourcePosition?: Position;
  targetPosition?: Position;
  parentId?: string;
  dragHandle?: string;
  zIndex?: number;
};
```

### EdgeProps（Custom Edge 接收的 props）

```typescript
type EdgeProps<TEdge extends Edge = Edge> = {
  id: string;
  source: string;
  target: string;
  sourceHandle?: string | null;
  targetHandle?: string | null;
  sourceX: number;   // 來源 Handle 的 x 座標（已轉換）
  sourceY: number;
  targetX: number;   // 目標 Handle 的 x 座標
  targetY: number;
  sourcePosition: Position;
  targetPosition: Position;
  data?: TEdge['data'];
  type?: string;
  animated?: boolean;
  selected?: boolean;
  deletable?: boolean;
  selectable?: boolean;
  focusable?: boolean;
  markerStart?: string;
  markerEnd?: string;
  label?: ReactNode;
  labelX?: number;
  labelY?: number;
  labelStyle?: CSSProperties;
  labelShowBg?: boolean;
  style?: CSSProperties;
  className?: string;
  interactionWidth?: number;
};
```

### Connection

```typescript
type Connection = {
  source: string;
  target: string;
  sourceHandle: string | null;
  targetHandle: string | null;
};
```

### Viewport

```typescript
type Viewport = { x: number; y: number; zoom: number };
```

### Position enum

```typescript
enum Position {
  Left = 'left',
  Top = 'top',
  Right = 'right',
  Bottom = 'bottom',
}
```

### FitViewOptions

```typescript
type FitViewOptions = {
  padding?: number;              // 預設 0.1
  includeHiddenNodes?: boolean;  // 預設 false
  minZoom?: number;
  maxZoom?: number;
  duration?: number;             // 動畫時長 (ms)
  nodes?: (Node | { id: string })[]; // 只 fit 指定節點
};
```

### Rect

```typescript
type Rect = { x: number; y: number; width: number; height: number };
```

---

## 重要使用注意事項

### 1. nodeTypes / edgeTypes 必須在模組頂層宣告

```typescript
// ✅ 模組頂層
const nodeTypes = { process: ProcessNode, decision: DecisionNode };

function App() {
  return <ReactFlow nodeTypes={nodeTypes} />;
}
```

### 2. Hook 效能層級

```
useStore(selector)     ← 最精確，可自訂 equality fn
useNodesData(id)       ← 訂閱特定節點 data
useViewport()          ← 只訂閱 viewport
useReactFlow()         ← 不觸發 re-render（按需查詢）
useNodes() / useEdges() ← 任何節點/邊線變更都觸發 re-render（謹慎使用）
```

### 3. 座標轉換（拖放 Sidebar 節點）

```typescript
const { screenToFlowPosition } = useReactFlow();

const onDrop = (e: DragEvent) => {
  const position = screenToFlowPosition({ x: e.clientX, y: e.clientY });
  addNode({ id: uuid(), type: 'process', position, data: {} });
};
```

### 4. Custom Node 常用模式

```typescript
import { memo } from 'react';
import { Handle, Position, NodeProps, useNodeId, NodeToolbar } from '@xyflow/react';

function MyNode({ data, selected }: NodeProps<MyNodeType>) {
  const nodeId = useNodeId(); // 取得自身 ID
  const updateNodeData = useFlowStore((s) => s.updateNodeData);

  return (
    <>
      <NodeToolbar isVisible={selected} position={Position.Top}>
        <button onClick={() => updateNodeData(nodeId!, { status: 'done' })}>✓</button>
      </NodeToolbar>
      <Handle type="target" position={Position.Left} />
      <div className="node-body">{data.label}</div>
      <Handle type="source" position={Position.Right} />
    </>
  );
}
export default memo(MyNode);
```

### 5. 等待節點初始化後執行 Layout

```typescript
const nodesInitialized = useNodesInitialized();

useEffect(() => {
  if (nodesInitialized) {
    applyLayout();
    fitView({ duration: 400 });
  }
}, [nodesInitialized]);
```

### 6. 動態 Handle 後必須更新 internals

```typescript
const updateNodeInternals = useUpdateNodeInternals();

const addHandle = useCallback(() => {
  setHandleCount(c => c + 1);
  updateNodeInternals(nodeId); // ← 告知 RF 重新計算 Handle 位置
}, [nodeId, updateNodeInternals]);
```
