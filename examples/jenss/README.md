# SynthetIQ JenSS Stress Fixtures

These scripts exercise SynthetIQ-shaped conversational concerns through
JenSS/Jenerator surfaces without making Jenerator a runtime dependency of this
package.

## Scripts

- `router-catalog-stress.jss` loads a route catalog, trains a small intent
  model, scores a probe input, trains Markov template continuity, and applies a
  confidence review policy.
- `evaluation-feedback-stress.jss` models evaluation feedback, correction
  reinforcement, and a low-confidence fallback gate for reusable dialogue
  training workflows.

## Runner

Run these from a Jenerator checkout by loading the SynthetIQ script path through
`BlueFission\Jenerator\Parsing\JenssParser` and
`BlueFission\Jenerator\Runtime\Interpreter`. Set the process working directory
to the SynthetIQ project root so JSON fixture paths resolve correctly.
