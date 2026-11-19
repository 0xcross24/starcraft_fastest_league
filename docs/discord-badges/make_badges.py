"""Ladder badge emojis: black box, border + grade in the tier colour.
Letter and sign are placed by their measured ink so every badge is centred."""
from PIL import Image, ImageDraw, ImageFont
import sys
S = 4  # supersample
COL = {'S': '#FFC800', 'A': '#3CE03C', 'B': '#88B4D4', 'C': '#F0D840', 'D': '#FF0000', 'E': '#A0A0A0'}
GRADES = ['S', 'A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'D-', 'E']
font = ImageFont.truetype('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', 52 * S)
BOX = (1.5, 25.5, 126.5, 102.5)  # same outline as the first set
BORDER, GAP, SIGN = 7, 3, 26     # px; sign = width of the + / − bars
CAP = 37                         # cap height in px

def badge(g, out):
    c = COL[g[0]]
    im = Image.new('RGBA', (128 * S, 128 * S), (0, 0, 0, 0))
    ImageDraw.Draw(im).rectangle([v * S for v in BOX], fill='black', outline=c, width=BORDER * S)
    ink = Image.new('L', im.size, 0)        # grade drawn as a mask, then centred by its real ink
    d = ImageDraw.Draw(ink)
    # letter ink box
    l, t, r, b = d.textbbox((0, 0), g[0], font=font)
    lw = r - l
    sw = (GAP + SIGN) * S if len(g) > 1 else 0
    x0 = 64 * S - (lw + sw) / 2           # left edge of the whole ink
    yc = (BOX[1] + BOX[3]) / 2 * S         # vertical centre of the box
    d.text((x0 - l, yc - (t + b) / 2), g[0], font=font, fill=255)
    if len(g) > 1:                          # draw the sign as bars, centred on the cap middle
        bar = 7 * S
        sx = x0 + lw + GAP * S
        d.rectangle([sx, yc - bar / 2, sx + SIGN * S, yc + bar / 2], fill=255)
        if g[1] == '+':
            cx = sx + SIGN * S / 2
            d.rectangle([cx - bar / 2, yc - SIGN * S / 2, cx + bar / 2, yc + SIGN * S / 2], fill=255)
    l, t, r, b = ink.getbbox()
    dx, dy = round(64 * S - (l + r) / 2), round(yc - (t + b) / 2)
    im.paste(Image.new('RGBA', im.size, c), (dx, dy), ink)
    im.resize((128, 128), Image.LANCZOS).save(out)

if __name__ == '__main__':
    d = sys.argv[1] if len(sys.argv) > 1 else '.'
    for g in GRADES:
        badge(g, f"{d}/sfl_{g[0]}{'_plus' if g.endswith('+') else '_minus' if g.endswith('-') else ''}.png")
