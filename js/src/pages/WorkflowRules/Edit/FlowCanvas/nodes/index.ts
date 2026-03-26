import EntranceNode from './EntranceNode'
import ActionNode from './ActionNode'
import ExitNode from './ExitNode'

/**
 * React Flow nodeTypes 註冊表
 * 必須使用 useMemo 或定義在元件外部以避免重複渲染
 */
export const nodeTypes = {
	entrance: EntranceNode,
	action: ActionNode,
	exit: ExitNode,
} as const
