<div
    style="display: flex !important; flex-direction: row !important; align-items: center !important; justify-content: center !important; gap: 8px;">
    <svg class="spinner-giro" style="width: 20px; height: 20px;" viewBox="0 0 24 24" fill="none">
        <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path style="opacity: 0.75;" fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
        </path>
    </svg>
    <span style="white-space: nowrap;">{{ $slot }}</span>
</div>
