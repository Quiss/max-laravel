# Changelog

## v0.1.7

- Retry only HTTP 400 with exact MAX code `attachment.not.ready` on POST /messages,
  reusing the uploaded attachment and identical payload.
- Bound message attempts and delays by one configurable 20-second deadline;
  default backoff 1/2/4/8 seconds allows at most five attempts.
- Make upload timeout configurable (default 45 seconds, previously 120), keeping
  default media HTTP/wait limits at 95 seconds per call.
- Preserve original Guzzle exceptions, status, and full response diagnostics.
  Do not retry other errors or ambiguous network failures.
- Add optional media keyboards while preserving existing positional/named calls;
  photo keyboard follows format. Video and document also accept format.
- Remove upload response dumps from missing-token error messages.
- Cover media retries, deadlines, keyboard payloads, and compatibility using mocks
  and a fake clock without real MAX requests or sleeping.
