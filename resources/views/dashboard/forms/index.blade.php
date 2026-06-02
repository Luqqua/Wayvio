@extends('layouts.sidebar')

@php
  $total      = (int) ($list['total'] ?? 0);
  $page       = (int) ($list['page'] ?? 1);
  $perPage    = (int) ($list['per_page'] ?? config('forms.dashboard_per_page', 25));
  $lastPage   = max(1, (int) ceil($total / max(1, $perPage)));
  $newCount   = (int) ($newCountsByForm[$formKey] ?? 0);
  $pageQuery  = ['form_key' => $formKey];
  $retentionDays    = (int) ($retentionDays ?? config('forms.retention_days', 180));
  $retentionMode    = (string) ($retentionMode ?? 'plan');
  $retentionGraceDays = (int) ($retentionGraceDays ?? config('billing.lifecycle.pending_deletion_grace_days', 1));
  $retentionPendingDeletionAt = trim((string) ($retentionPendingDeletionAt ?? ''));
  $retentionDeleteAfterAt = trim((string) ($retentionDeleteAfterAt ?? ''));
  $retentionDaysUntilPendingDeletion = isset($retentionDaysUntilPendingDeletion) ? (int) $retentionDaysUntilPendingDeletion : null;
  $retentionDaysUntilDelete = isset($retentionDaysUntilDelete) ? (int) $retentionDaysUntilDelete : null;
  $retentionPendingDeletionEstimated = (bool) ($retentionPendingDeletionEstimated ?? false);
  $retentionDeleteAfterEstimated = (bool) ($retentionDeleteAfterEstimated ?? false);
  $formsDashboardTimezone = trim((string) config('forms.dashboard_timezone', 'Europe/Berlin')) ?: 'Europe/Berlin';
  $formatSubmissionDate = static function (string $value) use ($formsDashboardTimezone): string {
      if ($value === '') return '-';
      try {
          $dt = \Carbon\Carbon::parse($value, 'UTC')->timezone($formsDashboardTimezone);
          return $dt->format('d.m.Y H:i') . ' Uhr';
      } catch (\Throwable) {
          return $value;
      }
  };
  $monthsDE = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
  $formatRetentionDateHuman = static function (string $value) use ($monthsDE): ?string {
      if ($value === '') return null;
      try {
          $dt = \Carbon\Carbon::parse($value);
          return $dt->day . '. ' . $monthsDE[$dt->month - 1] . ' ' . $dt->year;
      } catch (\Throwable) {
          return null;
      }
  };
  $pendingDateHuman = $formatRetentionDateHuman($retentionPendingDeletionAt);
  $deleteDateHuman  = $formatRetentionDateHuman($retentionDeleteAfterAt);
  $hasAtRiskSubmissions = $retentionMode === 'plan' && collect($submissions)->contains(function ($s) {
      $r = trim((string) ($s['retention_until'] ?? ''));
      return $r !== '' && \Carbon\Carbon::parse($r)->lt(now()->addDays(2));
  });
  $selectedPayload  = is_array($selectedSubmission['payload'] ?? null) ? $selectedSubmission['payload'] : null;
  $selectedStatus   = (string) ($selectedSubmission['status'] ?? '');
  $selectedEmail    = $selectedPayload ? (string) ($selectedPayload['email'] ?? '') : '';
  $selectedSubject  = $selectedPayload ? (string) ($selectedPayload['subject'] ?? '') : '';
  $formLabels = [
      'imprint_contact'  => 'Impressum',
      'hub_contact_block' => 'Block',
  ];
  $detailFields = $selectedPayload
      ? array_filter($selectedPayload, fn ($v) => $v !== '' && $v !== null && (is_string($v) || is_numeric($v)))
      : [];
@endphp

@section('content')
<div class="container-fluid content-inner mt-n5 pt-0 pb-4 ls-consistent-spacing forms-dashboard-page">

  <div class="row gx-3 gy-4">

    {{-- Title card --}}
    <div class="col-12">
      <div class="card rounded border-0 shadow-sm overflow-hidden forms-shell-card">
        <div class="card-body p-4 p-xl-5">
          <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-4">
            <div>
              <p class="text-uppercase text-primary small fw-semibold mb-1">{{ __('Dashboard') }}</p>
              <h3 class="mb-1">{{ __('Forms') }}</h3>
              <p class="text-muted mb-0">{{ __('Native Formulareingänge für den ausgewählten Hub.') }}</p>
              <div class="d-flex flex-wrap gap-2 mt-3">
                <span class="badge rounded-pill bg-soft-primary text-primary">{{ '@' . ($selectedHub->littlelink_name ?? $selectedHub->id) }}</span>
                <span class="badge rounded-pill bg-soft-info text-info">{{ number_format($total) }} {{ __('Entries') }}</span>
              </div>
            </div>
            <div class="forms-control-panel bg-soft-primary rounded p-3">
              <p class="text-uppercase text-primary small fw-semibold mb-2">{{ __('Form & Export') }}</p>
              <div class="btn-group w-100 mb-3" role="group" aria-label="{{ __('Form auswählen') }}">
                @foreach($forms as $key => $definition)
                  @php $isActiveTab = $formKey === $key; @endphp
                  <a class="btn btn-sm {{ $isActiveTab ? 'btn-primary' : 'btn-outline-primary' }}"
                     href="{{ route('forms.dashboard', ['form_key' => $key]) }}">
                    {{ $formLabels[$key] ?? __($definition['title'] ?? $key) }}
                    @if(($newCountsByForm[$key] ?? 0) > 0)
                      <span class="badge bg-danger rounded-pill ms-1">{{ $newCountsByForm[$key] }}</span>
                    @endif
                  </a>
                @endforeach
              </div>
              <a class="btn btn-primary btn-sm w-100"
                 href="{{ route('forms.dashboard.export', ['form_key' => $formKey]) }}">
                <i class="bi bi-download me-1"></i>{{ __('Export CSV') }}
              </a>
            </div>
          </div>

          {{-- Alerts inside card --}}
          @if(session('forms_dashboard_status'))
            <div class="alert alert-success mt-4 mb-0">{{ session('forms_dashboard_status') }}</div>
          @endif
          @if(empty($list['ok']))
            <div class="alert alert-warning mt-4 mb-0">{{ __('Forms API is currently unavailable or not configured.') }}</div>
          @endif
          @if($retentionMode === 'lifecycle_suspended')
            <div class="alert alert-warning d-flex align-items-start gap-2 mt-4 mb-0">
              <i class="bi bi-exclamation-triangle flex-shrink-0 mt-1"></i>
              <span>{{ __('Neue Einreichungen sind deaktiviert.') }}
                @if($deleteDateHuman)
                  {{ __('Bestehende Nachrichten werden am :date gelöscht', ['date' => $deleteDateHuman]) }}{{ ($retentionDeleteAfterEstimated ? ' (' . __('voraussichtlich') . ')' : '') }}.
                @elseif($pendingDateHuman)
                  {{ __('Bestehende Nachrichten werden ab :date zum Löschen vorgemerkt', ['date' => $pendingDateHuman]) }}{{ ($retentionPendingDeletionEstimated ? ' (' . __('voraussichtlich') . ')' : '') }}.
                @endif
              </span>
            </div>
          @elseif($retentionMode === 'lifecycle_pending_deletion')
            <div class="alert alert-danger d-flex align-items-start gap-2 mt-4 mb-0">
              <i class="bi bi-exclamation-octagon flex-shrink-0 mt-1"></i>
              <span>
                @if($deleteDateHuman)
                  {{ __('Nachrichten werden am :date endgültig gelöscht', ['date' => $deleteDateHuman]) }}{{ ($retentionDeleteAfterEstimated ? ' (' . __('voraussichtlich') . ')' : '') }}.
                @else
                  {{ __('Nachrichten werden in Kürze endgültig gelöscht.') }}
                @endif
              </span>
            </div>
          @elseif(!$formsAllowed)
            <div class="alert alert-warning d-flex align-items-start gap-2 mt-4 mb-0">
              <i class="bi bi-lock flex-shrink-0 mt-1"></i>
              <span>{{ __('Neue Einreichungen sind für diesen Hub deaktiviert. Bestehende Daten können exportiert oder gelöscht werden.') }}</span>
            </div>
          @elseif($hasAtRiskSubmissions)
            <div class="alert alert-warning d-flex align-items-start gap-2 mt-4 mb-0">
              <i class="bi bi-hourglass-split flex-shrink-0 mt-1"></i>
              <span>{{ __('Einige Nachrichten überschreiten das aktuelle Speicherlimit von :days Tagen und werden demnächst automatisch gelöscht. Exportiere sie jetzt um sie zu sichern.', ['days' => $retentionDays]) }}</span>
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Submissions list: hidden on mobile when a submission is open --}}
    <div class="col-xl-7{{ $selectedPayload ? ' d-none d-xl-block' : '' }}">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="mb-0">{{ __('Submissions') }}</h5>
            <div class="d-flex align-items-center gap-2">
              @if($newCount > 0)
                <form method="POST" action="{{ route('forms.dashboard.mark-all-read') }}" class="m-0">
                  @csrf
                  @method('PATCH')
                  <input type="hidden" name="form_key" value="{{ $formKey }}">
                  <input type="hidden" name="page" value="{{ $page }}">
                  <button type="submit" class="btn btn-outline-secondary btn-sm">
                    {{ __('Alle als gelesen markieren') }}
                  </button>
                </form>
              @endif
            </div>
          </div>

          @if(empty($submissions))
            <div class="forms-empty-state text-center py-5">
              <i class="bi bi-inbox fs-1 d-block mb-2"></i>
              <p class="mb-0">{{ __('Noch keine Nachrichten für dieses Formular.') }}</p>
            </div>
          @else
            <div class="list-group forms-submission-list">
              @foreach($submissions as $submission)
                @php
                  $isSelected = ($selectedSubmission['public_id'] ?? null) === ($submission['public_id'] ?? null);
                  $preview    = is_array($submission['preview'] ?? null) ? $submission['preview'] : [];
                  $itemTitle  = trim((string) ($preview['title'] ?? '')) ?: (string) ($submission['public_id'] ?? '');
                  $isNew      = ($submission['status'] ?? '') === 'new';
                  $rawDate    = (string) ($submission['created_at'] ?? '');
                  $displayDate = $formatSubmissionDate($rawDate);
                  $retentionUntilRaw = (string) ($submission['retention_until'] ?? '');
                  $subAtRisk = $retentionMode === 'plan'
                      && $retentionUntilRaw !== ''
                      && \Carbon\Carbon::parse($retentionUntilRaw)->lt(now()->addDays(2));
                @endphp
                <a class="list-group-item list-group-item-action forms-list-item {{ $isSelected ? 'active' : '' }}"
                   href="{{ route('forms.dashboard', array_merge($pageQuery, ['submission' => $submission['public_id'] ?? '', 'page' => $page])) }}">
                  <div class="d-flex align-items-center gap-3">
                    <span class="forms-unread-dot{{ $isNew ? ' forms-unread-dot--visible' : '' }}" aria-hidden="true"></span>
                    <div class="flex-fill min-w-0">
                      <div class="fw-semibold text-truncate">{{ $itemTitle }}</div>
                      <div class="small {{ $isSelected ? 'text-white-50' : 'text-muted' }}">{{ $displayDate }}</div>
                    </div>
                    @if($subAtRisk)
                      <span class="badge bg-warning text-dark flex-shrink-0 forms-at-risk-badge">Bald gelöscht</span>
                    @endif
                  </div>
                </a>
              @endforeach
            </div>

            @if($lastPage > 1)
              <nav class="d-flex align-items-center justify-content-between gap-2 mt-3"
                   aria-label="{{ __('Forms pagination') }}">
                <a class="btn btn-outline-secondary btn-sm {{ $page <= 1 ? 'disabled' : '' }}"
                   @if($page > 1) href="{{ route('forms.dashboard', array_merge($pageQuery, ['page' => $page - 1])) }}" @else aria-disabled="true" @endif>
                  {{ __('Previous') }}
                </a>
                <span class="text-muted small">{{ $page }} / {{ $lastPage }}</span>
                <a class="btn btn-outline-secondary btn-sm {{ $page >= $lastPage ? 'disabled' : '' }}"
                   @if($page < $lastPage) href="{{ route('forms.dashboard', array_merge($pageQuery, ['page' => $page + 1])) }}" @else aria-disabled="true" @endif>
                  {{ __('Next') }}
                </a>
              </nav>
            @endif
          @endif
        </div>
      </div>
    </div>

    {{-- Detail panel: hidden on mobile when nothing is selected --}}
    <div class="col-xl-5{{ !$selectedPayload ? ' d-none d-xl-block' : '' }}">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body p-4">
          @if($selectedPayload)

            {{-- Mobile: back to list --}}
            <a href="{{ route('forms.dashboard', array_merge($pageQuery, ['page' => $page])) }}"
               class="d-flex d-xl-none align-items-center gap-1 text-muted small text-decoration-none mb-3">
              <i class="bi bi-arrow-left"></i>{{ __('Back') }}
            </a>

            <div class="d-flex align-items-center justify-content-between mb-4">
              <h5 class="mb-0">{{ __('Details') }}</h5>
              <span class="badge rounded-pill {{ $selectedStatus === 'new' ? 'bg-success' : 'bg-secondary' }}">
                {{ $selectedStatus }}
              </span>
            </div>

            <dl class="forms-detail-list mb-4">
              {{-- Received timestamp --}}
              @php
                $detailRaw = (string) ($selectedSubmission['created_at'] ?? '');
                $detailDisplayDate = $formatSubmissionDate($detailRaw);
              @endphp
              <dt>{{ __('Received') }}</dt>
              <dd><span title="{{ $detailRaw }}">{{ $detailDisplayDate }}</span></dd>

              {{-- Dynamic payload fields --}}
              @foreach($detailFields as $fieldKey => $fieldValue)
                @php
                  $label = match($fieldKey) {
                    'name'    => __('Name'),
                    'email'   => __('Email'),
                    'subject' => __('Subject'),
                    'message', 'body' => __('Message'),
                    default   => ucfirst(str_replace('_', ' ', $fieldKey)),
                  };
                  $isEmailField   = $fieldKey === 'email';
                  $isMessageField = in_array($fieldKey, ['message', 'body'], true);
                @endphp
                <dt>{{ $label }}</dt>
                <dd class="{{ $isMessageField ? 'forms-message' : '' }}">
                  @if($isEmailField)<a href="mailto:{{ $fieldValue }}" class="text-decoration-none">{{ $fieldValue }}</a>@elseif($isMessageField){!! nl2br(e(trim((string) $fieldValue))) !!}@else{{ $fieldValue }}@endif
                </dd>
              @endforeach
            </dl>

            {{-- Primary actions --}}
            <div class="d-flex flex-wrap gap-2">
              @if($selectedEmail !== '')
                @php
                  $replyHref = 'mailto:' . $selectedEmail . '?subject=' . rawurlencode('Re: ' . ($selectedSubject ?: __('Ihre Anfrage')));
                @endphp
                <a href="{{ $replyHref }}" class="btn btn-primary btn-sm">
                  <i class="bi bi-reply me-1"></i>{{ __('Reply') }}
                </a>
              @endif

              @if($selectedStatus === 'new')
                <form method="POST"
                      action="{{ route('forms.dashboard.mark-read', ['publicId' => $selectedSubmission['public_id'] ?? '']) }}"
                      class="m-0">
                  @csrf
                  @method('PATCH')
                  <input type="hidden" name="form_key" value="{{ $formKey }}">
                  <input type="hidden" name="page" value="{{ $page }}">
                  <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-check2 me-1"></i>{{ __('Als gelesen markieren') }}
                  </button>
                </form>
              @endif
            </div>

            {{-- Danger zone --}}
            <div class="mt-4 pt-3 border-top">
              <button type="button" class="btn btn-outline-danger btn-sm"
                      data-bs-toggle="modal" data-bs-target="#forms-delete-modal">
                <i class="bi bi-trash me-1"></i>{{ __('Delete permanently') }}
              </button>
            </div>

          @else
            <div class="forms-empty-state text-center py-5">
              <i class="bi bi-envelope-open fs-1 d-block mb-2"></i>
              <p class="mb-0">{{ __('Wähle eine Nachricht aus, um sie anzusehen.') }}</p>
            </div>
          @endif
        </div>
      </div>
    </div>

  </div>
</div>

@if($selectedPayload)
  <div class="modal fade" id="forms-delete-modal" tabindex="-1"
       aria-labelledby="forms-delete-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="forms-delete-modal-title">
            {{ __('Submission dauerhaft löschen?') }}
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"
                  aria-label="{{ __('Close') }}"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">{{ __('Diese Submission wird endgültig gelöscht und kann nicht wiederhergestellt werden.') }}</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary"
                  data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <form method="POST"
                action="{{ route('forms.dashboard.delete', ['publicId' => $selectedSubmission['public_id'] ?? '']) }}"
                class="m-0">
            @csrf
            @method('DELETE')
            <input type="hidden" name="form_key" value="{{ $formKey }}">
            <button type="submit" class="btn btn-danger">
              <i class="bi bi-trash me-1"></i>{{ __('Dauerhaft löschen') }}
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
@endif

<style>
  /* Shell card border-radius */
  .forms-dashboard-page .forms-shell-card {
    border-radius: 1rem;
  }

  /* Control panel width */
  .forms-dashboard-page .forms-control-panel {
    width: min(100%, 22rem);
  }

  /* Submission list */
  .forms-submission-list .list-group-item {
    border-left: 0;
    border-right: 0;
  }

  /* Unread dot indicator */
  .forms-unread-dot {
    flex-shrink: 0;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: transparent;
    transition: background 0.15s;
  }
  .forms-unread-dot--visible {
    background: var(--bs-primary);
  }
  .list-group-item.active .forms-unread-dot--visible {
    background: rgba(255, 255, 255, 0.8);
  }

  /* Detail key-value grid */
  .forms-detail-list {
    display: grid;
    grid-template-columns: minmax(90px, auto) 1fr;
    gap: 8px 14px;
  }
  .forms-detail-list dt {
    color: var(--bs-secondary-color, #6c757d);
    font-weight: 600;
  }
  .forms-detail-list dd {
    margin: 0;
    min-width: 0;
    overflow-wrap: anywhere;
  }
  .forms-message {
    word-break: break-word;
  }

  /* At-risk badge on active (blue) list item */
  .list-group-item.active .forms-at-risk-badge {
    background: rgba(255, 255, 255, 0.2) !important;
    color: #fff !important;
  }

  /* Empty states */
  .forms-empty-state {
    color: var(--bs-secondary-color, #6c757d);
  }
  .forms-empty-state .bi {
    opacity: 0.35;
  }
</style>
@endsection
