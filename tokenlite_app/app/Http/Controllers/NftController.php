<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NFT;

class NftController extends Controller
{
    public function saveNft(Request $request)
    {
        $id = $request->input('id');
        $link = $request->input('link');

        $nft = new NFT;
        $nft->instagramid = $id;
        $nft->link = $link;
        $nft->save();

        $nftData = $this->buildNftData($id, $link);
        return response()->json($nftData);
    }

    public function getNft($id)
    {
        // $id = $request->input('id');

        $nft = NFT::where('instagramid', '=', $id)
            ->first();

        $json = $this->buildNftData($nft->instagramid, $nft->link);

        return response()->json($json);
    }

    function buildNftData($id, $imageUrl)
    {
        return [
            "name" => "Mende ($id)",
            "symbol" => "",
            "description" => "Solana Hackathon Demo ❤️.",
            "seller_fee_basis_points" => 1000,
            "external_url" => "https://www.bonuz.market/",
            "attributes" => [
                [
                    "trait_type" => "Demo",
                    "value" => "Best ever!"
                ],
                [
                    "trait_type" => "Best Chain",
                    "value" => "Solana"
                ],
                [
                    "trait_type" => "Product",
                    "value" => "Shines"
                ]
            ],
            "collection" => [
                "name" => "Mende",
                "family" => "Mende"
            ],
            "properties" => [
                "files" => [
                    [
                        "uri" => $imageUrl,
                        "type" => "image/png"
                    ]
                ],
                "category" => "image",
                "maxSupply" => 1,
                // "creators" => [
                //     [
                //         "address" => "74gDQgTZk9y5DTwtfjCki4jMoQwYs5FAY2QNh9dcLSRR",
                //         "share" => 75
                //     ],
                //     [
                //         "address" => "AhQszsH9VfzHMeW4ByN39SYnuVMKtF13m2fxo78SWKXB",
                //         "share" => 25
                //     ]
                // ]
            ],
            "image" => $imageUrl
        ];
    }
}
