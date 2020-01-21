import * as YAML from 'yaml';

type CodeMap = import('./CodeMap');
type SassValue = import('./SassValue');

export interface ParsedSource {
  readonly path: string;
  readonly source: string;
  readonly ast: YAML.ast.Document;
  readonly map: CodeMap;
}

export type TravelrScalar = string | number | boolean;
export interface TravelrObject {
  [key: string]: TravelrScalar | TravelrObject;
}
export type TravelrArray = Array<TravelrData>;

export type TravelrData = TravelrScalar | TravelrObject | TravelrArray;

export interface TransformedSource extends ParsedSource {
  readonly data: TravelrData;
}

export type ScalarTransformer = (
  node: YAML.ast.ScalarNode,
  doc: YAML.ast.Document,
  map: CodeMap,
) => string | number | boolean | SassValue;
