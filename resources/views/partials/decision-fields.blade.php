{{-- Client decision evidence inputs. Expects $outcomes (value => label). --}}
<x-field name="outcome" type="select" label="Outcome" :options="$outcomes" required />
<x-field name="decided_by_name" label="Decided by (client contact)" required />
<x-field name="source_reference" label="Evidence" placeholder="Email 3 Apr, call notes, signed form…" required />
<x-field name="decided_at" type="date" label="Decision date" :value="now()" max="{{ today()->toDateString() }}" required />
<x-field name="notes" type="textarea" label="Notes / feedback" />
