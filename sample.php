
@extends('layouts.app')

@section('title', 'Team')

@section('main')
    {{-- ===== Page Title ===== --}}
    <section class="page-title" style="background-image: url({{ asset('images/inner/page-title-bg.jpg') }});">
        <div class="auto-container">
            <div class="title-outer">
                <ul class="page-breadcrumb wow fadeInUp" data-wow-delay=".3s">
                    <li><a href="{{ route('home') }}">Home</a></li>
                    <li>Our Team</li>
                </ul>
                <h1 class="title wow fadeInUp" data-wow-delay=".5s">Our Team</h1>
            </div>
        </div>
    </section>

    {{-- ===== Team Grid (click to open modal) ===== --}}
    <section class="team-section-3 fix section-padding">
        <div class="auto-container">
            <div class="row g-4">

                {{-- CEO (Temitope Oduseso) --}}
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
                    <div class="team-box-items-4 mt-0 team-card" data-name="Temitope Oduseso"
                        data-title="Group Managing Director/CEO" data-image="{{ asset('images/ceo.png') }}"
                        data-bio="Mr. Tope is a Fellow of the following professional bodies: Institute of Chartered Accountants of Nigeria (ICAN); Chartered Institute of Taxation of Nigeria (CITN); and Nigerian Institute of Management (Chartered).
He also earned a Master's Degree in International Finance from London Metropolitan University, UK; and Fellowship Award in Managing Sustainable Development from Maastricht School of Management, Netherlands.
He obtained his HND (Upper Credit) in Accountancy in 1994. Temitope has over 25 years’ experience across Audit, Banking, Investment Management, Financial Analysis, Developmental Finance, Credit/Risk Management, and Human Capital Development.">
                        <div class="team-image">
                            <img src="{{ asset('images/ceo.png') }}" alt="Temitope Oduseso">
                            <img src="{{ asset('images/ceo.png') }}" alt="Temitope Oduseso">
                        </div>
                        <button type="button" class="socials open-team-modal"
                            aria-label="View details for Temitope Oduseso">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                        <div class="content-box">
                            <h3 class="title"><a href="javascript:void(0)">Temitope Oduseso</a></h3>
                            <p class="sub-title">Group Managing Director/CEO</p>
                        </div>
                    </div>
                </div>

                {{-- ISAAC ONAOLAPO – Managing Director --}}
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
                    <div class="team-box-items-4 mt-0 team-card" data-name="Isaac Onaolapo" data-title="Managing Director"
                        data-image="{{ asset('images/isaac.png') }}"
                        data-bio="Isaac Onaolapo is a seasoned Investment Advisor with close to two decades of experience across Commercial Banking, Technology, Insurance, Investment Banking and Fintech.
He has generated cumulative revenue of over $50 million working with Bank PHB (Keystone Bank), Old Mutual (South Africa), G+D Currency Technology (Germany), Meristem Securities Limited and CredPal.
He is passionate about leveraging quality service and products to close client pain points across private sector, government and individuals.
Isaac is a Mentor at Lagos Business School (Africa Retail Academy), an Associate of CIIN, and currently studying to qualify as an Associate of the CIS.
He joined Richgreen Master’s Group in 2025 as the pioneer Managing Director of Richgreen Master’s Capital Ltd.">
                        <div class="team-image">
                            <img src="{{ asset('images/isaac.png') }}" alt="Isaac Onaolapo">
                            <img src="{{ asset('images/isaac.png') }}" alt="Isaac Onaolapo">
                        </div>
                        <button type="button" class="socials open-team-modal" aria-label="View details for Isaac Onaolapo">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                        <div class="content-box">
                            <h3 class="title"><a href="javascript:void(0)">Isaac Onaolapo</a></h3>
                            <p class="sub-title">Managing Director</p>
                        </div>
                    </div>
                </div>


                     {{-- OLUWASEUN ANDU – MD (UK) & Group Director, Tech/Strategy --}}
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
                    <div class="team-box-items-4 mt-0 team-card" data-name="Oluwaseun Andu"
                        data-title="MD (UK) & Group Director, Technology, Digital Transformation & Strategy"
                        data-image="{{ asset('images/seun.png') }}" {{-- ✅ FIXED: match the visible card image --}}
                        data-bio="Oluwaseun Andu is the Managing Director of Richgreen Master Limited (UK) and Group Director of Technology, Digital Transformation, and Strategy.
He currently serves as Senior Consultant, Technology Delivery at a leading digital bank in Canada, with 13+ years driving transformation across Nigeria, the UK, the US, and Canada.
He has held managerial roles with Access Bank, First Bank, FCMB, and Stanbic IBTC, and worked with National Bank Canada and Equitable Bank Canada—delivering value across finance, insurance, telecoms, transportation, e-commerce, health, and IT.
He holds a B.Sc. in Computer Engineering Technology and an MBA in Project Management & Digital Innovation (UK), and holds certifications including Agile PM, PMP, CSM, SAFe ASM, CSP, ITIL, and MCP.">
                        <div class="team-image">
                            <img src="{{ asset('images/seun.png') }}" alt="Oluwaseun Andu">
                            <img src="{{ asset('images/seun.png') }}" alt="Oluwaseun Andu">
                        </div>
                        <button type="button" class="socials open-team-modal" aria-label="View details for Oluwaseun Andu">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                        <div class="content-box">
                            <h3 class="title"><a href="javascript:void(0)">Oluwaseun Andu</a></h3>
                            <p class="sub-title">MD (UK) & Group Director, Technology & Strategy</p>
                        </div>
                    </div>
                </div>


                {{-- VINCENT ADEGBITE – Group Head, Business Development --}}
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
                    <div class="team-box-items-4 mt-0 team-card" data-name="Vincent Adegbite"
                        data-title="Group Head, Business Development"
                        data-image="{{ asset('images/babs.png') }}"
                        data-bio="Vincent Adegbite holds an HND in Surveying & Geoinformatics (1997) and an MBA in HR Management from Lagos State University. He is a Chartered member of the Nigeria Institute of Management.
He began his banking career in 2000 with Trans International Bank and joined Legacy NBM Bank (now Sterling Bank) in 2004, rising to Manager through consistent performance.
He has worked across Foreign Operations, Public Sector, Product Development, Consumer Lending, Retail & Consumer Banking, with extensive knowledge of Commercial Banking. He has also attended courses both locally and internationally.
Vincent is a highly focused Retail Banking professional with excellent financial skills, strong client satisfaction record, and a proven team player—success-driven and ready to take calculated risks.">
                        <div class="team-image">
                            <img style="height: 305px" src="{{ asset('images/babs.png') }}" alt="Vincent Adegbite">
                            <img src="{{ asset('images/babs.png') }}" alt="Vincent Adegbite">
                        </div>
                        <button type="button" class="socials open-team-modal"
                            aria-label="View details for Vincent Adegbite">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                        <div class="content-box">
                            <h3 class="title"><a href="javascript:void(0)">Vincent Adegbite</a></h3>
                            <p class="sub-title">Group Head, Business Development</p>
                        </div>
                    </div>
                </div>

           
            </div>
        </div>
    </section>



    {{-- ===== Modal (centered, consistent layout with white padding) ===== --}}
<div id="teamModal" class="hidden" role="dialog" aria-modal="true" aria-labelledby="teamModalTitle"
     style="position:fixed; inset:0; z-index:99999; display:none;">
  {{-- Backdrop --}}
  <div id="teamModalBackdrop"
       style="position:absolute; inset:0; background:rgba(0,0,0,.6); z-index:1;"></div>

  {{-- Centering layer --}}
  <div id="teamModalCenter"
       style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; overflow:auto; padding:16px; z-index:2;">
    {{-- Panel --}}
    <div id="teamModalPanel" class="bg-white team-modal-panel">
      <button type="button" id="teamModalClose" aria-label="Close" class="team-modal-close">
        <i class="fa-solid fa-xmark"></i>
      </button>

      {{-- Body --}}
      <div class="team-modal-grid">
        {{-- LEFT: fixed portrait image with white padding --}}
        <div class="team-modal-left">
          <div class="team-modal-photo-wrap">
            <img id="teamModalImage" src="" alt="" class="team-modal-photo">
          </div>
        </div>

        {{-- RIGHT: text scrollable if long --}}
        <div class="team-modal-right">
          <h3 id="teamModalTitle" class="team-modal-name">—</h3>
          <p id="teamModalRole" class="team-modal-role">—</p>
          <div id="teamModalBio" class="team-modal-bio">—</div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  /* Panel sizing + centering */
  .team-modal-panel{
    position:relative;
    width:100%;
    max-width:1100px;
    max-height:calc(100vh - 80px);
    border-radius:16px;
    box-shadow:0 20px 60px rgba(0,0,0,.35);
    padding:24px;
    overflow:hidden;
  }
  .team-modal-close{
    position:absolute; top:12px; right:12px;
    width:40px; height:40px; border:none; cursor:pointer;
    border-radius:999px; display:inline-flex; align-items:center; justify-content:center;
    background:#f3f4f6; z-index:4;
  }
  .team-modal-close i{ pointer-events:none; }

  /* Grid layout */
  .team-modal-grid{
    display:grid;
    grid-template-columns: 500px 1fr;
    gap:28px;
    align-items:start;
    height:100%;
  }

  /* LEFT column: photo with white padding */
  .team-modal-left{
    padding:20px; /* ✅ white space around image */
    display:flex;
    align-items:center;
    justify-content:center;
  }
  .team-modal-photo-wrap{
    width:100%;
    border-radius:16px;
    overflow:hidden;
    background:#f0f2f5;
    aspect-ratio: 4 / 5;   /* consistent portrait shape */
  }
  .team-modal-photo{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
  }

  /* RIGHT column */
  .team-modal-right{
    min-height:0;
    max-height:100%;
    overflow:auto;
    padding-right:6px;
  }

  /* Typography */
  .team-modal-name{
    font-size: clamp(24px, 3.2vw, 40px);
    font-weight: 800;
    margin: 6px 0 6px;
    line-height:1.15;
  }
  .team-modal-role{
    color:#0f766e;
    font-weight:600;
    margin:0 0 14px;
  }
  .team-modal-bio{
    white-space:pre-line;
    line-height:1.7;
  }

  /* Mobile view */
  @media (max-width: 991.98px){
    .team-modal-grid{ grid-template-columns: 1fr; }
    .team-modal-left{ padding:10px; }
    .team-modal-photo-wrap{ aspect-ratio: 16 / 9; }
    .team-modal-right{ max-height:none; overflow:visible; }
  }

  body.modal-open{ overflow:hidden; }
</style>

<script>
(function(){
  const modal   = document.getElementById('teamModal');
  const overlay = document.getElementById('teamModalBackdrop');
  const btnX    = document.getElementById('teamModalClose');

  const imgEl   = document.getElementById('teamModalImage');
  const titleEl = document.getElementById('teamModalTitle');
  const roleEl  = document.getElementById('teamModalRole');
  const bioEl   = document.getElementById('teamModalBio');

  // Open on click anywhere on a .team-card (or trigger)
  document.addEventListener('click', function(e){
    const trigger = e.target.closest('.open-team-modal, .team-card .team-image, .team-card .content-box a, .team-card');
    if(!trigger) return;
    const card = trigger.closest('.team-card');
    if(!card) return;

    titleEl.textContent = card.dataset.name  || '—';
    roleEl.textContent  = card.dataset.title || '—';
    imgEl.src           = card.dataset.image || '';
    imgEl.alt           = (card.dataset.name || '') + ' photo';
    bioEl.textContent   = card.dataset.bio   || '—';

    modal.style.display = 'block';
    modal.classList.remove('hidden');
    document.body.classList.add('modal-open');
    document.addEventListener('keydown', onKeydown);
  });

  function closeModal(){
    modal.style.display = 'none';
    modal.classList.add('hidden');
    document.body.classList.remove('modal-open');
    document.removeEventListener('keydown', onKeydown);
  }
  function onKeydown(e){ if(e.key === 'Escape') closeModal(); }

  overlay.addEventListener('click', closeModal);
  btnX.addEventListener('click', closeModal);
})();
</script>


@endsection
