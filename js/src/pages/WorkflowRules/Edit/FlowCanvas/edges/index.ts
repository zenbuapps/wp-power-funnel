import CustomEdge from './CustomEdge'

/**
 * React Flow edgeTypes 註冊表
 * 必須使用 useMemo 或定義在元件外部以避免重複渲染
 */
export const edgeTypes = {
	custom: CustomEdge,
} as const
