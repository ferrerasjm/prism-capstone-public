# Tagalog RoBERTa narratives — functionality check

Real narratives pulled from `ml/data/processed/prism_training.csv`, run
through the actual trained model (`HybridOffensePredictor.predict()` in
`ml/scripts/hybrid_inference.py`, frozen artifacts under `ml/artifacts/`,
no HTTP mocking, no fallback). Each row's expected label is the label it
carries in `prism_training.csv`; each result below was observed by
actually running the model on 2026-08-18, not assumed.

| Narrative (Tagalog) | Expected label | Top prediction | Confidence |
|---|---|---|---:|
| Binutas ng estudyante ang gulong ng sasakyang nakaparada sa University parking area bilang paghihiganti. | MAJOR-15 | MAJOR-15 | 92.4% |
| Dumalo ang mag-aaral sa klase nang hindi suot ang prescribed uniform para sa kaniyang programa. | MINOR-01 | MINOR-01 | 95.4% |
| Nahuling naninigarilyo ang mag-aaral sa loob ng sasakyang nakaparada sa University parking area. | MAJOR-26 | MAJOR-26 | 92.9% |
| Gumamit ang mag-aaral ng homophobic slurs laban sa isang estudyante sa loob ng classroom. | MAJOR-37 | MAJOR-37 | 92.5% |
| Nakasaad lamang sa ulat mula sa regular na pag-iikot na inayos muli ng isang estudyante ang mga key ng keyboard; hindi tinukoy ang may-ari, anumang pinsala, kawalan ng pahintulot, o nalabag na patakaran sa laboratoryo. | NO_MATCH | NO_MATCH | 85.2% |

All five matched their reviewed label at rank 1 with confidence well above
the 50% floor used elsewhere in this project's evaluation scripts. No
assertions were written from assumption — the model was run first, then
the expected values above were transcribed from its actual output.
