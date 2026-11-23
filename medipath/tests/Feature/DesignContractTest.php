<?php

function designTokens(): string
{
    return file_get_contents(resource_path('sass/_tokens.scss'));
}

it('ports the mandated design tokens verbatim', function () {
    expect(designTokens())
        ->toContain('#0E7C86')
        ->toContain('#0B2545')
        ->toContain('#F6F9FB')
        ->toContain('#E2E8F0')
        ->toContain('#16A34A')
        ->toContain('#D97706')
        ->toContain('#DC2626')
        ->toContain('#2563EB')
        ->toContain('#B91C1C')
        ->toContain('--mp-focus-ring')
        ->toContain('data-theme')
        ->toContain('data-bs-theme');
});

it('keeps emergency red out of routine destructive tokens', function () {
    expect(designTokens())->toContain('$mp-danger:         #DC2626')
        ->toContain('$mp-emergency:      #B91C1C');
});

it('gives every component file a COMPONENT-SPEC citation header', function () {
    $files = glob(resource_path('js/Components/*/*.tsx')) ?: [];
    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $this->assertStringContainsString('COMPONENT-SPEC', file_get_contents($file), basename($file));
    }
});

it('does not hardcode the currency glyph in UI components or pages', function () {
    $files = array_merge(
        glob(resource_path('js/Components/*/*.tsx')) ?: [],
        glob(resource_path('js/Pages/*/*.tsx')) ?: [],
        glob(resource_path('js/Layouts/*.tsx')) ?: [],
    );

    foreach ($files as $file) {
        $this->assertStringNotContainsString('৳', file_get_contents($file), basename($file));
    }
});

it('does not hardcode role labels inside the component library', function () {
    $files = glob(resource_path('js/Components/*/*.tsx')) ?: [];

    foreach ($files as $file) {
        $contents = file_get_contents($file);
        foreach (["'Admin'", '"Admin"', "'Doctor'", '"Doctor"', "'Pharmacist'", '"Pharmacist"', "'Patient'", '"Patient"'] as $literal) {
            $this->assertStringNotContainsString($literal, $contents, basename($file).' must receive role labels as props');
        }
    }
});

it('confines emergency classes to the sanctioned emergency surfaces', function () {
    $patterns = ['btn-emergency', 'badge-emergency', 'msg-emergency', 'alert-emergency', 'cta-emergency', 'emg-banner', 'call-card', 'is-emergency'];
    $allowed = [
        'EmergencyBlocks.tsx',
        'ChatbotWidget.tsx',
        'ConfirmModal.tsx',
        'StatusBadge.tsx',
        'ToastStack.tsx',
        'PublicNavbar.tsx',
    ];

    $files = glob(resource_path('js/Components/*/*.tsx')) ?: [];

    foreach ($files as $file) {
        $contents = file_get_contents($file);
        foreach ($patterns as $pattern) {
            if (str_contains($contents, $pattern)) {
                $this->assertContains(basename($file), $allowed, "{$pattern} used outside the emergency ledger");
            }
        }
    }
});

it('ships the four-state contract on the canonical data view', function () {
    $dataView = file_get_contents(resource_path('js/Components/Data/DataView.tsx'));

    expect($dataView)
        ->toContain("'skeleton'")
        ->toContain("'empty'")
        ->toContain("'error'")
        ->toContain("'success'")
        ->toContain('Retry');
});

it('keeps the responsive thresholds and touch target in the token contract', function () {
    expect(designTokens())
        ->toContain('--mp-touch:44px')
        ->toContain('--mp-sidebar-w:264px')
        ->toContain('--mp-navbar-h:64px');
});
