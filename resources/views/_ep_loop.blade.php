{{-- Endpoint accordion loop partial --}}
{{-- Expects: $eps (array of endpoint definitions), $mc (method → CSS class map) --}}
@php
$roleColor = [
    'Public'     => 'bg-gray-100 text-gray-600',
    'Admin'      => 'bg-red-50 text-red-700',
    'Instructor' => 'bg-amber-50 text-amber-700',
    'Student'    => 'bg-emerald-50 text-emerald-700',
];
$codeColor = [
    '2' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    '4' => 'bg-rose-50 text-rose-700 border-rose-200',
    '5' => 'bg-orange-50 text-orange-700 border-orange-200',
];
@endphp

@foreach($eps as $ep)
@php
[$method, $name, $path, $auth, $roles, $desc, $reqJson, $resJson, $codes] = $ep;
$mid = strtolower(preg_replace('/\s+/','-',$name)).'-'.uniqid();
@endphp
<div>
  {{-- Accordion Header --}}
  <button onclick="toggleAccordion(this)"
          class="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition-colors text-left group">
    <div class="flex items-center gap-3 min-w-0">
      <span class="text-[10px] font-bold px-2 py-0.5 rounded uppercase flex-shrink-0 {{ $mc[$method] ?? 'mp' }}">{{ $method }}</span>
      <span class="font-medium text-gray-900 text-sm truncate">{{ $name }}</span>
      <span class="text-xs text-gray-400 font-mono hidden sm:block truncate">{{ $path }}</span>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
      @if($auth)
        <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 uppercase tracking-wide">Auth</span>
      @endif
      <svg class="chev w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
      </svg>
    </div>
  </button>

  {{-- Accordion Body --}}
  <div class="acc-body border-t border-gray-100 bg-slate-50">
    <div class="p-5 space-y-4">

      {{-- Top row: description + roles + path --}}
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex-1 min-w-0">
          <p class="text-sm text-gray-700 leading-relaxed mb-2">{{ $desc }}</p>
          <code class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded font-mono">{{ $path }}</code>
        </div>
        <div class="flex flex-wrap gap-1.5">
          @foreach($roles as $role)
            <span class="role-chip {{ $roleColor[$role] ?? 'bg-gray-100 text-gray-600' }} border border-opacity-40">{{ $role }}</span>
          @endforeach
        </div>
      </div>

      {{-- Two-column: request | response --}}
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">

        {{-- Request Body --}}
        <div>
          <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1.5">
            {{ $reqJson ? 'Request Body' : 'Request Body' }}
          </p>
          @if($reqJson)
            <div class="code-block">{{ json_encode(json_decode($reqJson), JSON_PRETTY_PRINT) }}</div>
          @else
            <div class="code-block text-gray-500 italic">No request body required</div>
          @endif
        </div>

        {{-- Response --}}
        <div>
          <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1.5">Response · 200 / 201</p>
          <div class="code-block">{{ json_encode(json_decode($resJson), JSON_PRETTY_PRINT) }}</div>
        </div>

      </div>

      {{-- Status Codes --}}
      <div>
        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2">Status Codes</p>
        <div class="flex flex-wrap gap-2">
          @foreach($codes as $codeEntry)
            @php $code=$codeEntry[0]; $label=$codeEntry[1]; $note=$codeEntry[2]??''; @endphp
            <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border text-xs {{ $codeColor[substr($code,0,1)] ?? 'bg-gray-100 text-gray-600 border-gray-200' }}">
              <span class="font-bold font-mono">{{ $code }}</span>
              <span class="font-medium">{{ $label }}</span>
              @if($note)
                <span class="opacity-70">— {{ $note }}</span>
              @endif
            </div>
          @endforeach
        </div>
      </div>

    </div>
  </div>
</div>
@endforeach
