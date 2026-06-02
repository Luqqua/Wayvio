import React, { useState } from 'react';

export function AnalyticsCard({
    title,
    description,
    numbers,
    graph,
    defaultView = 'graph',
    lockedMessage = null,
    allowGraph = true,
    showToggle = true,
    footer = null,
}) {
    const [view, setView] = useState(defaultView);
    const resolvedView = showToggle ? view : defaultView;
    const showNumbers = resolvedView === 'numbers' || !allowGraph;
    const showSwitcher = showToggle && allowGraph && !lockedMessage;

    return (
        <div className="card border-0 shadow-sm h-100 analytics-card">
            <div className="card-header bg-transparent border-0 pb-0 pt-4 px-4">
                <div className="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3">
                    <div>
                        <p className="text-uppercase text-muted small mb-1">{title}</p>
                        {description ? <h6 className="mb-0">{description}</h6> : <h6 className="mb-0">Analytics module</h6>}
                    </div>
                    {showSwitcher ? (
                        <ul className="nav nav-pills analytics-card-toggle" role="tablist" aria-label={`${title} view switch`}>
                            <li className="nav-item">
                                <button
                                    type="button"
                                    className={`nav-link ${showNumbers ? 'active' : ''}`}
                                    onClick={() => setView('numbers')}
                                >
                                    Table
                                </button>
                            </li>
                            <li className="nav-item">
                                <button
                                    type="button"
                                    className={`nav-link ${!showNumbers ? 'active' : ''}`}
                                    onClick={() => setView('graph')}
                                >
                                    Chart
                                </button>
                            </li>
                        </ul>
                    ) : null}
                </div>
            </div>
            <div className="card-body d-flex flex-column pt-3 px-4 pb-4">
                <div className="d-flex flex-column w-100 h-100" style={{ minHeight: 260 }}>
                    <div className="flex-grow-1">
                        {!lockedMessage && !showNumbers && allowGraph ? (
                            <div className="mb-3 small text-muted d-flex align-items-center gap-2">
                                <i className="bi bi-bar-chart-line"></i>
                                <span>Interactive chart view</span>
                            </div>
                        ) : null}
                        {lockedMessage ? (
                            <p className="text-muted mb-0 small">{lockedMessage}</p>
                        ) : showNumbers ? (
                            numbers
                        ) : (
                            graph
                        )}
                    </div>
                    {!lockedMessage && footer ? <div className="mt-auto pt-2 text-end">{footer}</div> : null}
                </div>
            </div>
        </div>
    );
}

export default AnalyticsCard;
