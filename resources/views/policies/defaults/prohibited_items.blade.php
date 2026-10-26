{{-- Default Prohibited Items Policy, shown until an admin publishes their own in Settings → Policies. --}}
@php $policy = fn (string $key) => route('policies.show', $key); @endphp
<style>
    .pp h2 { margin: 1.75rem 0 .5rem; font-family: 'Nunito', ui-sans-serif, system-ui; font-size: 1.1rem; font-weight: 800; color: #222; }
    .pp h2:first-of-type { margin-top: 0; }
    .pp h3 { margin: 1rem 0 .35rem; font-size: .95rem; font-weight: 700; color: #222; }
    .pp p { margin: .5rem 0; }
    .pp ul { margin: .4rem 0 .6rem 1.2rem; list-style: disc; }
    .pp li { margin: .2rem 0; }
    .pp a { color: #fa4e1c; font-weight: 600; }
    .pp .pp-note { border-radius: .6rem; background: #FFF7F3; border: 1px solid #FAD7C8; padding: .75rem 1rem; color: #57534E; }
    .pp table { width: 100%; margin: .5rem 0; border-collapse: collapse; font-size: .85rem; }
    .pp th, .pp td { border: 1px solid #ECE7E3; padding: .5rem .6rem; text-align: left; vertical-align: top; }
    .pp th { background: #FAFAF9; font-weight: 700; color: #222; }
</style>
<div class="pp">
    <p class="pp-note">
        <strong>Effective date:</strong> October 22, 2026. This policy lists items that may <strong>never</strong> be sold on ALVY,
        and items that may be sold only with the right permits. It applies to every listing, photo, description and chat.
        It is part of the <a href="{{ $policy('seller_policy') }}">Seller Policy</a> and our
        <a href="{{ $policy('terms_of_service') }}">Terms of Service</a>.
    </p>

    <h2>1. Why we have this policy</h2>
    <p>
        ALVY must follow Philippine law, keep buyers, riders and sorting-center staff safe, and protect the brands and
        creators whose work is sold here. Our parcels are carried by riders on motorcycles and handled at sorting centers,
        so some items are also banned simply because they can't be shipped safely. If an item is illegal to sell in the
        Philippines, it is prohibited on ALVY even if it is not named below.
    </p>

    <h2>2. Prohibited items</h2>
    <p>These items can't be listed, sold, offered or advertised on ALVY under any circumstances.</p>

    <h3>Weapons and explosives</h3>
    <ul>
        <li>Firearms, ammunition, gun parts and accessories (magazines, silencers), and replicas that can be mistaken for real guns.</li>
        <li>Explosives, firecrackers, pyrotechnics and fireworks.</li>
        <li>Knives and bladed weapons made to injure (butterfly and balisong knives, switchblades, brass knuckles, bladed weapons sold as weapons), stun guns, tasers and pepper sprays.</li>
    </ul>

    <h3>Drugs, medicines and health products</h3>
    <ul>
        <li>Illegal drugs and controlled substances, and drug paraphernalia (RA 9165).</li>
        <li>Prescription medicines, and any medicine, vitamin, food supplement, medical device or cosmetic that is not registered with the <strong>Food and Drug Administration (FDA)</strong>.</li>
        <li>Products that claim to cure, treat or prevent diseases without FDA approval.</li>
        <li>Vapes, e-cigarettes and heated tobacco products not compliant with RA 11900, and any tobacco or vape product for buyers under 18.</li>
    </ul>

    <h3>Alcohol</h3>
    <ul>
        <li>Alcoholic beverages, including homemade or unlabelled liquor.</li>
    </ul>

    <h3>Hazardous and dangerous goods</h3>
    <ul>
        <li>Flammable, corrosive, toxic or pressurised items: gasoline and other fuels, LPG and other gas tanks, aerosols, paints and thinners, strong acids, pesticides and chemicals.</li>
        <li>Loose lithium batteries and power banks that are damaged, swollen or not in a device or proper packaging.</li>
        <li>Radioactive materials, and any item that leaks, smells strongly or could hurt someone handling the parcel.</li>
    </ul>

    <h3>Animals, plants and wildlife</h3>
    <ul>
        <li>Live animals, including pets, livestock, birds, fish and insects.</li>
        <li>Endangered or protected wildlife and anything made from it (ivory, turtle shell, coral, pangolin, protected orchids), and wildlife products without DENR permits (RA 9147).</li>
        <li>Human remains, body parts and bodily fluids.</li>
    </ul>

    <h3>Fake, stolen and illegal goods</h3>
    <ul>
        <li>Counterfeit or "replica", "class A", "OEM" or "inspired" copies of branded goods, and items that infringe trademarks, copyrights or patents (RA 8293), including pirated movies, music, games, software and e-books.</li>
        <li>Stolen goods, or items you don't own or have the right to sell.</li>
        <li>Fake or forged documents, IDs, licenses, certificates, diplomas, receipts and government forms, and equipment to make them.</li>
        <li>Counterfeit money, and real Philippine currency sold above face value.</li>
    </ul>

    <h3>Personal data, accounts and digital goods</h3>
    <ul>
        <li>Personal information of other people (lists of names, numbers, emails, IDs), and hacked or shared accounts.</li>
        <li>Hacking tools, malware, and devices or services to unlock, jailbreak or bypass security without permission.</li>
        <li>SIM cards already registered in someone else's name, and services to get around SIM registration (RA 11934).</li>
    </ul>

    <h3>Adult, offensive and harmful content</h3>
    <ul>
        <li>Pornography and sexually explicit material, and any item that sexualises minors.</li>
        <li>Items that promote hate, violence, terrorism or discrimination.</li>
        <li>Gambling services, lottery tickets, and items used for illegal gambling.</li>
    </ul>

    <h3>Government, police and military items</h3>
    <ul>
        <li>Uniforms, badges, insignia and equipment of the police, military or government that could be used to impersonate an official.</li>
        <li>Government-issued property, and items marked "not for sale" or "for government use".</li>
    </ul>

    <h3>Services and things that aren't products</h3>
    <ul>
        <li>Services, loans, investments, cryptocurrency, gift card reselling, pre-orders for items you don't have, and "mystery" listings that hide what the buyer gets.</li>
        <li>Recalled products, and products banned by any Philippine government agency.</li>
    </ul>

    <h2>3. Restricted items (allowed with conditions)</h2>
    <p>These items may be sold only if you meet the conditions below and can show proof when ALVY asks.</p>
    <table>
        <tr><th style="width:34%;">Item</th><th>Condition</th></tr>
        <tr><td>Food and drinks, including homemade food</td><td>Sealed and labelled with ingredients and expiry date, not perishable in transit, and FDA-registered where required. No raw meat, seafood or anything needing refrigeration.</td></tr>
        <tr><td>Cosmetics, skin care and food supplements</td><td>FDA-registered or notified, in original sealed packaging, with no medical claims.</td></tr>
        <tr><td>Plants and seeds</td><td>Not protected species; must follow Bureau of Plant Industry rules.</td></tr>
        <tr><td>Kitchen and work knives, tools, sports equipment</td><td>Sold for their normal use, not as weapons, and packed so they can't cut anyone handling the parcel.</td></tr>
        <tr><td>Electronics with batteries</td><td>Batteries installed in or packed with the device, undamaged, with genuine branding.</td></tr>
        <tr><td>Used and second-hand items</td><td>Clearly listed as used, with honest photos and defects described. Used underwear, swimwear and cosmetics are not allowed.</td></tr>
        <tr><td>Medical devices (thermometers, BP monitors…)</td><td>FDA-registered, new and sealed.</td></tr>
        <tr><td>Branded goods and licensed merchandise</td><td>Genuine only. Be ready to show proof of purchase or authorisation from the brand.</td></tr>
    </table>

    <h2>4. Listing rules that also apply</h2>
    <ul>
        <li>Don't hide a prohibited item behind a different name, category or photo, or sell it "as a gift" with another item.</li>
        <li>Don't use brand names you are not selling (for example "like Nike" or "same as Apple") to attract searches.</li>
        <li>Photos and descriptions must not show prohibited items, even in the background.</li>
    </ul>

    <h2>5. What happens if you break this policy</h2>
    <p>
        Every new listing is reviewed by ALVY before it goes live, and live listings can be reviewed at any time, including
        after a buyer or another seller reports them. Depending on how serious it is and whether it has happened before, ALVY may:
    </p>
    <ul>
        <li>reject or remove the listing and record a violation on your store;</li>
        <li>give a warning;</li>
        <li>cancel affected orders and refund the buyers;</li>
        <li>suspend or permanently deactivate your store; and</li>
        <li>report illegal items or activity to the proper authorities, as the law requires.</li>
    </ul>
    <p>
        If you believe a listing was removed by mistake, contact us with the details and any permits or proof, and we will review it.
    </p>

    <h2>6. Reporting a listing</h2>
    <p>
        If you see an item that shouldn't be on ALVY, message us or email
        <a href="mailto:alvyshop27@gmail.com">alvyshop27@gmail.com</a> with the product link. We review every report.
    </p>

    <h2>7. Changes to this policy</h2>
    <p>
        Laws and safety rules change, and we may add items to this list at any time. Sellers are told about important changes
        through the Seller Centre or by email, and must remove listings that become prohibited.
    </p>
</div>
