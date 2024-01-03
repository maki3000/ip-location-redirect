<div class="popup-wrapper">

    <div class="popup-overlay"></div>

    {{ loaderImage }}

    <div class="popup-window" role="dialog" aria-hidden="true">
        <div class="popup-content">
            <div class="popup-header">
                <h2>{{ redirect_title }}</h2>
                <div class="closer">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="17.707" height="17.707" viewBox="0 0 17.707 17.707">
                        <g transform="translate(-985.146 -244.461)">
                            <path class="--stroke" d="M3570.813,15829.813l17,17" transform="translate(-2585.313 -15584.998)" fill="none" stroke="#000" stroke-width="1"/>
                            <path class="--stroke" d="M3587.813,15829.813l-17,17" transform="translate(-2585.313 -15584.998)" fill="none" stroke="#000" stroke-width="1"/>
                        </g>
                    </svg>
                </div>
            </div>
            <div class="popup-text-content">
                <p class="popup-info">{{ redirect_back_choices }}</p>
                <ul>{{ redirectList }}</ul>
                <p>{{ redirect_info }}</p>
            </div>
            <div class="pop-text-content">
                <button class="popup-close">
                    Ok
                </button>
            </div>
        </div>
    </div>

</div>
